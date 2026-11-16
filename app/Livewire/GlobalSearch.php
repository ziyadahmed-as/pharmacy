<?php

namespace App\Livewire;

use App\Models\Customer;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Staff;
use App\Reports\ReportRegistry;
use App\Support\Navigation;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class GlobalSearch extends Component
{
    public string $query = '';

    public bool $isOpen = false;

    public function open(): void
    {
        $this->isOpen = true;
    }

    public function close(): void
    {
        $this->isOpen = false;
        $this->query = '';
    }

    public function render(ReportRegistry $reports)
    {
        $user = Auth::user();
        $results = [];

        $q = trim($this->query);

        if ($user && strlen($q) >= 2) {
            $term = '%'.$q.'%';

            // 1. Pages / Navigation items (accessible to current user)
            $pageResults = [];
            foreach (Navigation::forUser($user) as $group) {
                foreach ($group['items'] as $item) {
                    $label = __($item['label']);
                    $groupLabel = __($group['label'] ?? 'nav.group.'.$group['key']);
                    if (stripos($label, $q) !== false || stripos($item['route'], $q) !== false || stripos($groupLabel, $q) !== false) {
                        $pageResults[] = [
                            'title' => $label,
                            'subtitle' => $groupLabel,
                            'url' => route($item['route']),
                            'badge' => __('search.type_page'),
                            'icon' => 'page',
                        ];
                    }
                }
            }
            if (! empty($pageResults)) {
                $results['pages'] = array_slice($pageResults, 0, 5);
            }

            // 2. Products (by brand, generic name, barcode) - requires catalogue.view
            if ($user->canAccessBusinessData() && $user->hasPermission('catalogue.view')) {
                $products = Product::query()
                    ->where('brand', 'like', $term)
                    ->orWhere('generic_name', 'like', $term)
                    ->orWhere('barcode', 'like', $term)
                    ->limit(5)
                    ->get();

                if ($products->isNotEmpty()) {
                    $results['products'] = $products->map(fn ($p) => [
                        'title' => $p->brand.' '.($p->strength ?? ''),
                        'subtitle' => $p->generic_name.($p->barcode ? ' • Barcode: '.$p->barcode : ''),
                        'url' => route('catalogue.show', $p),
                        'badge' => __('search.type_product'),
                        'icon' => 'product',
                    ])->all();
                }
            }

            // 3. Customers (by name, phone) - requires customers.view
            if ($user->canAccessBusinessData() && $user->hasPermission('customers.view')) {
                $customers = Customer::query()
                    ->where('name', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->limit(5)
                    ->get();

                if ($customers->isNotEmpty()) {
                    $results['customers'] = $customers->map(fn ($c) => [
                        'title' => $c->name,
                        'subtitle' => $c->phone.($c->email ? ' • '.$c->email : ''),
                        'url' => route('customers.show', $c),
                        'badge' => __('search.type_customer'),
                        'icon' => 'customer',
                    ])->all();
                }
            }

            // 4. Prescriptions (by prescription_number, patient/customer name, prescriber) - requires prescriptions.view
            if ($user->canAccessBusinessData() && $user->hasPermission('prescriptions.view')) {
                $prescriptions = Prescription::query()
                    ->with('customer')
                    ->where('prescription_number', 'like', $term)
                    ->orWhere('prescriber_name', 'like', $term)
                    ->orWhereHas('customer', function ($cq) use ($term) {
                        $cq->where('name', 'like', $term);
                    })
                    ->limit(5)
                    ->get();

                if ($prescriptions->isNotEmpty()) {
                    $results['prescriptions'] = $prescriptions->map(fn ($rx) => [
                        'title' => $rx->prescription_number,
                        'subtitle' => ($rx->customer?->name ?? '').' • '.$rx->prescriber_name,
                        'url' => route('prescriptions.show', $rx),
                        'badge' => __('search.type_prescription'),
                        'icon' => 'prescription',
                    ])->all();
                }
            }

            // 5. Receipts / Sales (by receipt_no) - requires shifts.open or shifts.view
            if ($user->canAccessBusinessData() && ($user->hasPermission('shifts.open') || $user->hasPermission('shifts.view'))) {
                $sales = Sale::query()
                    ->where('receipt_no', 'like', $term)
                    ->limit(5)
                    ->get();

                if ($sales->isNotEmpty()) {
                    $results['receipts'] = $sales->map(fn ($s) => [
                        'title' => $s->receipt_no,
                        'subtitle' => ($s->completed_at ? $s->completed_at->format('Y-m-d H:i') : '').' • '.number_format($s->total_amount / 100, 2).' ETB',
                        'url' => route('sales.receipt', $s),
                        'badge' => __('search.type_receipt'),
                        'icon' => 'receipt',
                    ])->all();
                }
            }

            // 6. Staff (by name, title, phone) - requires staff.manage
            if ($user->canAccessBusinessData() && $user->hasPermission('staff.manage')) {
                $staffMembers = Staff::query()
                    ->where('name', 'like', $term)
                    ->orWhere('title', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->limit(5)
                    ->get();

                if ($staffMembers->isNotEmpty()) {
                    $results['staff'] = $staffMembers->map(fn ($sm) => [
                        'title' => $sm->name,
                        'subtitle' => ($sm->title ?: '').($sm->phone ? ' • '.$sm->phone : ''),
                        'url' => route('staff.show', $sm),
                        'badge' => __('search.type_staff'),
                        'icon' => 'staff',
                    ])->all();
                }
            }

            // 7. Reports - requires access-business-data
            if ($user->canAccessBusinessData()) {
                $reportMatches = [];
                foreach ($reports->availableFor($user) as $rep) {
                    if (stripos($rep->name(), $q) !== false || stripos($rep->description(), $q) !== false || stripos($rep->key(), $q) !== false) {
                        $reportMatches[] = [
                            'title' => $rep->name(),
                            'subtitle' => $rep->description(),
                            'url' => route('reports.run', ['type' => $rep->key()]),
                            'badge' => __('search.type_report'),
                            'icon' => 'report',
                        ];
                    }
                }
                if (! empty($reportMatches)) {
                    $results['reports'] = array_slice($reportMatches, 0, 5);
                }
            }
        }

        return view('livewire.global-search', [
            'results' => $results,
            'query' => $q,
        ]);
    }
}
