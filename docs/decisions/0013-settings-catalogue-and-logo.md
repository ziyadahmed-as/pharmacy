# UC-38 lists setting groups but not every key.

**Status:** accepted (Stage 1)

## Context
App\Support\SettingsCatalogue lists every key used by Stages 1 to 6 with type, BRD default or Not set, and a required flag. profile.logo is stored on the private disk; rendering it on receipts belongs to the receipt use case.

## Decision
Add keys to the catalogue as OQ answers arrive.

## Consequence / revisit when

