<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Subcategory;
use Illuminate\Database\Seeder;

/**
 * Kategori & subkategori awal. Idempotent: hanya menambah yang belum ada,
 * tidak mengubah/menghapus perubahan yang dibuat IT Admin.
 */
class CategorySeeder extends Seeder
{
    public const CATEGORIES = [
        'Hardware' => ['PC / Laptop', 'Printer', 'Monitor', 'Keyboard / Mouse', 'Peripheral'],
        'Software & Application' => ['POS / Cashier', 'PMS / Hotel System', 'Accounting Software', 'System Taking Order', 'Office Application', 'Other Application'],
        'Network & Internet' => ['WiFi', 'LAN', 'Router', 'Switch', 'Internet Connection', 'VPN'],
        'CCTV & Security' => ['CCTV Camera', 'DVR / NVR', 'Monitoring', 'Storage', 'Access Control'],
        'Server & Infrastructure' => ['Server', 'Database', 'Backup', 'Domain / Hosting', 'Infrastructure'],
        'User & Account' => ['User Access', 'Email', 'Password Reset', 'Account Creation'],
        'Maintenance' => ['Troubleshooting', 'Installation', 'Configuration', 'Preventive Maintenance'],
        'Request & Development' => ['New Application', 'Feature Request', 'System Improvement', 'Other IT Request'],
    ];

    public function run(): void
    {
        $order = 0;
        foreach (self::CATEGORIES as $categoryName => $subs) {
            $category = Category::firstOrCreate(['name' => $categoryName], ['sort_order' => ++$order, 'is_active' => true]);

            foreach ($subs as $i => $subName) {
                Subcategory::firstOrCreate(
                    ['category_id' => $category->id, 'name' => $subName],
                    ['sort_order' => $i + 1, 'is_active' => true],
                );
            }
        }
    }
}
