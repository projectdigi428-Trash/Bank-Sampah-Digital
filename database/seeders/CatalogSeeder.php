<?php

namespace Database\Seeders;

use App\Models\Reward;
use App\Models\CollectionPoint;
use App\Models\WasteType;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => 'Plastik PET', 'price_per_kg' => 3000, 'description' => 'Botol plastik PET bersih dan kering.'],
            ['name' => 'Kertas', 'price_per_kg' => 2500, 'description' => 'Kertas dan koran yang sudah dipilah.'],
            ['name' => 'Kardus', 'price_per_kg' => 2000, 'description' => 'Kardus bersih tanpa lapisan plastik.'],
            ['name' => 'Logam', 'price_per_kg' => 5000, 'description' => 'Sampah logam yang dapat didaur ulang.'],
            ['name' => 'Minyak Jelantah', 'price_per_kg' => 4000, 'description' => 'Minyak jelantah yang disimpan dalam wadah tertutup.'],
        ] as $wasteType) {
            WasteType::updateOrCreate(['name' => $wasteType['name']], $wasteType);
        }

        $collectionPoint = CollectionPoint::updateOrCreate(
            ['name' => 'Bank Sampah Induk'],
            ['address' => 'Alamat contoh, silakan ubah melalui API.', 'is_active' => true],
        );
        $collectionPoint->wasteTypes()->sync(WasteType::where('is_active', true)->pluck('id'));

        foreach ([
            ['name' => 'Sabun cuci', 'points_required' => 5000, 'stock' => 20, 'description' => 'Sabun cuci ramah lingkungan.'],
            ['name' => 'Bibit tanaman', 'points_required' => 3000, 'stock' => 30, 'description' => 'Bibit sayuran untuk ditanam di rumah.'],
            ['name' => 'Voucher belanja', 'points_required' => 10000, 'stock' => 10, 'description' => 'Voucher belanja kebutuhan harian.'],
        ] as $reward) {
            Reward::updateOrCreate(['name' => $reward['name']], $reward);
        }
    }
}
