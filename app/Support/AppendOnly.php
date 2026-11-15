<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Migration helper for database-level append-only triggers,
 * immutability enforcement, and CHECK constraints across
 * SQLite, MySQL/MariaDB, and PostgreSQL.
 */
class AppendOnly
{
    /**
     * Create append-only and immutability triggers for the table.
     *
     * @param  string  $table  Table name to protect
     * @param  bool  $blockUpdate  Whether to block updates
     * @param  bool  $blockDelete  Whether to block deletes
     * @param  array<string, mixed>  $options  Configuration options
     */
    public static function create(string $table, bool $blockUpdate = true, bool $blockDelete = true, array $options = []): void
    {
        $driver = DB::connection()->getDriverName();
        self::drop($table, $blockUpdate, $blockDelete);

        $statements = self::getTriggerSql($driver, $table, $blockUpdate, $blockDelete, $options);
        foreach ($statements as $sql) {
            DB::unprepared($sql);
        }
    }

    /**
     * Drop triggers and functions created for the table.
     */
    public static function drop(string $table, bool $dropUpdate = true, bool $dropDelete = true): void
    {
        $driver = DB::connection()->getDriverName();
        $statements = self::getDropSql($driver, $table, $dropUpdate, $dropDelete);
        foreach ($statements as $sql) {
            DB::unprepared($sql);
        }
    }

    /**
     * Add a CHECK constraint to a table.
     * On MySQL 8 and PostgreSQL, uses ALTER TABLE ADD CONSTRAINT CHECK (...).
     * On SQLite, uses BEFORE INSERT and BEFORE UPDATE triggers.
     */
    public static function addCheckConstraint(string $table, string $name, string $expression, ?string $sqliteExpression = null): void
    {
        $driver = DB::connection()->getDriverName();
        self::dropCheckConstraint($table, $name);

        $statements = self::getCheckConstraintSql($driver, $table, $name, $expression, $sqliteExpression);
        foreach ($statements as $sql) {
            DB::unprepared($sql);
        }
    }

    /**
     * Drop a CHECK constraint from a table.
     */
    public static function dropCheckConstraint(string $table, string $name): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::unprepared("DROP TRIGGER IF EXISTS `{$name}_insert`");
            DB::unprepared("DROP TRIGGER IF EXISTS `{$name}_update`");

            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            try {
                DB::statement("ALTER TABLE `{$table}` DROP CONSTRAINT IF EXISTS `{$name}`");
            } catch (\Throwable) {
                try {
                    DB::statement("ALTER TABLE `{$table}` DROP CHECK `{$name}`");
                } catch (\Throwable) {
                    // Ignore if constraint does not exist
                }
            }

            return;
        }

        if ($driver === 'pgsql') {
            try {
                DB::statement("ALTER TABLE `{$table}` DROP CONSTRAINT IF EXISTS `{$name}`");
            } catch (\Throwable) {
                // Ignore if constraint does not exist
            }

            return;
        }
    }

    /**
     * Generate SQL trigger creation statements for the specified driver.
     *
     * @return array<int, string>
     */
    public static function getTriggerSql(string $driver, string $table, bool $blockUpdate = true, bool $blockDelete = true, array $options = []): array
    {
        return match ($driver) {
            'sqlite' => self::getSqliteTriggerSql($table, $blockUpdate, $blockDelete, $options),
            'mysql', 'mariadb' => self::getMysqlTriggerSql($table, $blockUpdate, $blockDelete, $options),
            'pgsql' => self::getPgsqlTriggerSql($table, $blockUpdate, $blockDelete, $options),
            default => [],
        };
    }

    /**
     * Generate SQL trigger drop statements for the specified driver.
     *
     * @return array<int, string>
     */
    public static function getDropSql(string $driver, string $table, bool $dropUpdate = true, bool $dropDelete = true): array
    {
        return match ($driver) {
            'sqlite' => self::getSqliteDropSql($table, $dropUpdate, $dropDelete),
            'mysql', 'mariadb' => self::getMysqlDropSql($table, $dropUpdate, $dropDelete),
            'pgsql' => self::getPgsqlDropSql($table, $dropUpdate, $dropDelete),
            default => [],
        };
    }

    /**
     * Generate CHECK constraint SQL for the driver.
     *
     * @return array<int, string>
     */
    public static function getCheckConstraintSql(string $driver, string $table, string $name, string $expression, ?string $sqliteExpression = null): array
    {
        if ($driver === 'sqlite') {
            $expr = $sqliteExpression ?? self::toSqliteRowExpr($expression);

            return [
                "CREATE TRIGGER `{$name}_insert` BEFORE INSERT ON `{$table}` FOR EACH ROW WHEN NOT ({$expr}) BEGIN SELECT RAISE(ABORT, 'CHECK constraint failed: {$name}'); END;",
                "CREATE TRIGGER `{$name}_update` BEFORE UPDATE ON `{$table}` FOR EACH ROW WHEN NOT ({$expr}) BEGIN SELECT RAISE(ABORT, 'CHECK constraint failed: {$name}'); END;",
            ];
        }

        if (in_array($driver, ['mysql', 'mariadb', 'pgsql'], true)) {
            return [
                "ALTER TABLE `{$table}` ADD CONSTRAINT `{$name}` CHECK ({$expression})",
            ];
        }

        return [];
    }

    /**
     * Generate DROP CHECK constraint SQL for the driver.
     *
     * @return array<int, string>
     */
    public static function getDropCheckConstraintSql(string $driver, string $table, string $name): array
    {
        if ($driver === 'sqlite') {
            return [
                "DROP TRIGGER IF EXISTS `{$name}_insert`",
                "DROP TRIGGER IF EXISTS `{$name}_update`",
            ];
        }

        if ($driver === 'mysql') {
            return [
                "ALTER TABLE `{$table}` DROP CHECK `{$name}`",
            ];
        }

        if (in_array($driver, ['mariadb', 'pgsql'], true)) {
            return [
                "ALTER TABLE `{$table}` DROP CONSTRAINT IF EXISTS `{$name}`",
            ];
        }

        return [];
    }

    /**
     * Helper to convert an expression like "quantity >= 0" to "NEW.quantity >= 0" for SQLite triggers.
     */
    public static function toSqliteRowExpr(string $expression): string
    {
        // Replace un-prefixed word identifiers before operators with NEW.<identifier>
        return preg_replace('/\b([a-zA-Z_][a-zA-Z0-9_]*)\b(?!\s*[\(\'])/', 'NEW.$1', $expression) ?? $expression;
    }

    // --------------------------------------------------------------------------
    // SQLite SQL Generation
    // --------------------------------------------------------------------------

    private static function getSqliteTriggerSql(string $table, bool $blockUpdate, bool $blockDelete, array $options): array
    {
        $statements = [];

        if ($table === 'sales') {
            if ($blockUpdate) {
                $statements[] = "CREATE TRIGGER sales_no_update BEFORE UPDATE ON sales
WHEN OLD.status IN ('completed', 'voided')
  AND (
    NEW.id != OLD.id
    OR NEW.receipt_no != OLD.receipt_no
    OR NEW.shift_id != OLD.shift_id
    OR NEW.user_id != OLD.user_id
    OR NEW.total != OLD.total
    OR NEW.idempotency_key != OLD.idempotency_key
    OR NEW.completed_at != OLD.completed_at
    OR (NEW.customer_id IS NOT OLD.customer_id)
    OR (NEW.prescription_id IS NOT OLD.prescription_id)
  )
BEGIN
    SELECT RAISE(ABORT, 'sales cannot be modified once completed; only status and reprint_count may be updated');
END;";
            }

            if ($blockDelete) {
                $statements[] = "CREATE TRIGGER sales_no_delete BEFORE DELETE ON sales
WHEN OLD.status IN ('completed', 'voided')
BEGIN
    SELECT RAISE(ABORT, 'Completed sales cannot be deleted');
END;";
            }

            return $statements;
        }

        if (in_array($table, ['sale_lines', 'payments'], true)) {
            if ($blockUpdate) {
                $statements[] = "CREATE TRIGGER {$table}_no_update BEFORE UPDATE ON {$table}
WHEN (SELECT status FROM sales WHERE sales.id = OLD.sale_id) IN ('completed', 'voided')
BEGIN
    SELECT RAISE(ABORT, '{$table} cannot be updated once the sale is completed');
END;";
            }

            if ($blockDelete) {
                $statements[] = "CREATE TRIGGER {$table}_no_delete BEFORE DELETE ON {$table}
WHEN (SELECT status FROM sales WHERE sales.id = OLD.sale_id) IN ('completed', 'voided')
BEGIN
    SELECT RAISE(ABORT, '{$table} cannot be deleted once the sale is completed');
END;";
            }

            return $statements;
        }

        if ($blockUpdate) {
            $msg = "{$table} is append-only: updates are forbidden";
            $statements[] = "CREATE TRIGGER {$table}_no_update BEFORE UPDATE ON {$table}
BEGIN
    SELECT RAISE(ABORT, '{$msg}');
END;";
        }

        if ($blockDelete) {
            $msg = $table === 'approval_requests'
                ? 'approval_requests is delete-protected: deletes are forbidden'
                : "{$table} is append-only: deletes are forbidden";

            $statements[] = "CREATE TRIGGER {$table}_no_delete BEFORE DELETE ON {$table}
BEGIN
    SELECT RAISE(ABORT, '{$msg}');
END;";
        }

        return $statements;
    }

    private static function getSqliteDropSql(string $table, bool $dropUpdate, bool $dropDelete): array
    {
        $statements = [];
        if ($dropUpdate) {
            $statements[] = "DROP TRIGGER IF EXISTS {$table}_no_update";
        }
        if ($dropDelete) {
            $statements[] = "DROP TRIGGER IF EXISTS {$table}_no_delete";
        }

        return $statements;
    }

    // --------------------------------------------------------------------------
    // MySQL / MariaDB SQL Generation
    // --------------------------------------------------------------------------

    private static function getMysqlTriggerSql(string $table, bool $blockUpdate, bool $blockDelete, array $options): array
    {
        $statements = [];

        if ($table === 'sales') {
            if ($blockUpdate) {
                $statements[] = "CREATE TRIGGER `sales_no_update` BEFORE UPDATE ON `sales`
FOR EACH ROW
BEGIN
    IF OLD.status IN ('completed', 'voided') THEN
        IF NOT (
            NEW.id <=> OLD.id
            AND NEW.receipt_no <=> OLD.receipt_no
            AND NEW.shift_id <=> OLD.shift_id
            AND NEW.user_id <=> OLD.user_id
            AND NEW.total <=> OLD.total
            AND NEW.idempotency_key <=> OLD.idempotency_key
            AND NEW.completed_at <=> OLD.completed_at
            AND NEW.customer_id <=> OLD.customer_id
            AND NEW.prescription_id <=> OLD.prescription_id
        ) THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'sales cannot be modified once completed; only status and reprint_count may be updated';
        END IF;
    END IF;
END";
            }

            if ($blockDelete) {
                $statements[] = "CREATE TRIGGER `sales_no_delete` BEFORE DELETE ON `sales`
FOR EACH ROW
BEGIN
    IF OLD.status IN ('completed', 'voided') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Completed sales cannot be deleted';
    END IF;
END";
            }

            return $statements;
        }

        if (in_array($table, ['sale_lines', 'payments'], true)) {
            if ($blockUpdate) {
                $statements[] = "CREATE TRIGGER `{$table}_no_update` BEFORE UPDATE ON `{$table}`
FOR EACH ROW
BEGIN
    DECLARE v_status VARCHAR(50);
    SELECT status INTO v_status FROM sales WHERE id = OLD.sale_id;
    IF v_status IN ('completed', 'voided') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$table} cannot be updated once the sale is completed';
    END IF;
END";
            }

            if ($blockDelete) {
                $statements[] = "CREATE TRIGGER `{$table}_no_delete` BEFORE DELETE ON `{$table}`
FOR EACH ROW
BEGIN
    DECLARE v_status VARCHAR(50);
    SELECT status INTO v_status FROM sales WHERE id = OLD.sale_id;
    IF v_status IN ('completed', 'voided') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$table} cannot be deleted once the sale is completed';
    END IF;
END";
            }

            return $statements;
        }

        if ($blockUpdate) {
            $msg = "{$table} is append-only: updates are forbidden";
            $statements[] = "CREATE TRIGGER `{$table}_no_update` BEFORE UPDATE ON `{$table}`
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$msg}'";
        }

        if ($blockDelete) {
            $msg = $table === 'approval_requests'
                ? 'approval_requests is delete-protected: deletes are forbidden'
                : "{$table} is append-only: deletes are forbidden";

            $statements[] = "CREATE TRIGGER `{$table}_no_delete` BEFORE DELETE ON `{$table}`
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$msg}'";
        }

        return $statements;
    }

    private static function getMysqlDropSql(string $table, bool $dropUpdate, bool $dropDelete): array
    {
        $statements = [];
        if ($dropUpdate) {
            $statements[] = "DROP TRIGGER IF EXISTS `{$table}_no_update`";
        }
        if ($dropDelete) {
            $statements[] = "DROP TRIGGER IF EXISTS `{$table}_no_delete`";
        }

        return $statements;
    }

    // --------------------------------------------------------------------------
    // PostgreSQL SQL Generation (PL/pgSQL functions)
    // --------------------------------------------------------------------------

    private static function getPgsqlTriggerSql(string $table, bool $blockUpdate, bool $blockDelete, array $options): array
    {
        $statements = [];

        if ($table === 'sales') {
            $fnName = 'prevent_sales_modification';
            $statements[] = "CREATE OR REPLACE FUNCTION {$fnName}()
RETURNS TRIGGER AS $$
BEGIN
    IF TG_OP = 'DELETE' THEN
        IF OLD.status IN ('completed', 'voided') THEN
            RAISE EXCEPTION 'Completed sales cannot be deleted';
        END IF;
        RETURN OLD;
    ELSIF TG_OP = 'UPDATE' THEN
        IF OLD.status IN ('completed', 'voided') THEN
            IF NEW.id IS DISTINCT FROM OLD.id
                OR NEW.receipt_no IS DISTINCT FROM OLD.receipt_no
                OR NEW.shift_id IS DISTINCT FROM OLD.shift_id
                OR NEW.user_id IS DISTINCT FROM OLD.user_id
                OR NEW.total IS DISTINCT FROM OLD.total
                OR NEW.idempotency_key IS DISTINCT FROM OLD.idempotency_key
                OR NEW.completed_at IS DISTINCT FROM OLD.completed_at
                OR NEW.customer_id IS DISTINCT FROM OLD.customer_id
                OR NEW.prescription_id IS DISTINCT FROM OLD.prescription_id THEN
                RAISE EXCEPTION 'sales cannot be modified once completed; only status and reprint_count may be updated';
            END IF;
        END IF;
        RETURN NEW;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;";

            if ($blockUpdate) {
                $statements[] = "CREATE TRIGGER sales_no_update BEFORE UPDATE ON sales FOR EACH ROW EXECUTE FUNCTION {$fnName}();";
            }
            if ($blockDelete) {
                $statements[] = "CREATE TRIGGER sales_no_delete BEFORE DELETE ON sales FOR EACH ROW EXECUTE FUNCTION {$fnName}();";
            }

            return $statements;
        }

        if (in_array($table, ['sale_lines', 'payments'], true)) {
            $fnName = "prevent_{$table}_modification";
            $statements[] = "CREATE OR REPLACE FUNCTION {$fnName}()
RETURNS TRIGGER AS $$
DECLARE
    v_status text;
BEGIN
    SELECT status INTO v_status FROM sales WHERE id = OLD.sale_id;
    IF v_status IN ('completed', 'voided') THEN
        RAISE EXCEPTION '{$table} cannot be updated or deleted once the sale is completed';
    END IF;
    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    ELSE
        RETURN NEW;
    END IF;
END;
$$ LANGUAGE plpgsql;";

            if ($blockUpdate) {
                $statements[] = "CREATE TRIGGER {$table}_no_update BEFORE UPDATE ON {$table} FOR EACH ROW EXECUTE FUNCTION {$fnName}();";
            }
            if ($blockDelete) {
                $statements[] = "CREATE TRIGGER {$table}_no_delete BEFORE DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION {$fnName}();";
            }

            return $statements;
        }

        if ($blockUpdate) {
            $fnName = "prevent_{$table}_update";
            $statements[] = "CREATE OR REPLACE FUNCTION {$fnName}()
RETURNS TRIGGER AS $$
BEGIN
    RAISE EXCEPTION '{$table} is append-only: updates are forbidden';
END;
$$ LANGUAGE plpgsql;";

            $statements[] = "CREATE TRIGGER {$table}_no_update BEFORE UPDATE ON {$table} FOR EACH ROW EXECUTE FUNCTION {$fnName}();";
        }

        if ($blockDelete) {
            $msg = $table === 'approval_requests'
                ? 'approval_requests is delete-protected: deletes are forbidden'
                : "{$table} is append-only: deletes are forbidden";

            $fnName = "prevent_{$table}_delete";
            $statements[] = "CREATE OR REPLACE FUNCTION {$fnName}()
RETURNS TRIGGER AS $$
BEGIN
    RAISE EXCEPTION '{$msg}';
END;
$$ LANGUAGE plpgsql;";

            $statements[] = "CREATE TRIGGER {$table}_no_delete BEFORE DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION {$fnName}();";
        }

        return $statements;
    }

    private static function getPgsqlDropSql(string $table, bool $dropUpdate, bool $dropDelete): array
    {
        $statements = [];
        if ($dropUpdate) {
            $statements[] = "DROP TRIGGER IF EXISTS {$table}_no_update ON {$table}";
            $statements[] = "DROP FUNCTION IF EXISTS prevent_{$table}_update()";
        }
        if ($dropDelete) {
            $statements[] = "DROP TRIGGER IF EXISTS {$table}_no_delete ON {$table}";
            $statements[] = "DROP FUNCTION IF EXISTS prevent_{$table}_delete()";
        }
        if (in_array($table, ['sales', 'sale_lines', 'payments'], true)) {
            $statements[] = "DROP FUNCTION IF EXISTS prevent_{$table}_modification()";
        }

        return $statements;
    }
}
