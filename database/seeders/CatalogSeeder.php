<?php

namespace Database\Seeders;

use App\Models\InvitationTemplate;
use App\Models\Package;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            [
                'name' => 'Essential',
                'description' => 'Digital invitation, RSVP tracking and guest list for intimate weddings.',
                'price' => 99,
                'currency' => 'USD',
                'features' => ['1 invitation design', 'Up to 150 guests', 'RSVP dashboard', 'QR code'],
            ],
            [
                'name' => 'Premium',
                'description' => 'Everything in Essential plus seating plans, gift tracking and gallery.',
                'price' => 249,
                'currency' => 'USD',
                'features' => ['3 invitation designs', 'Up to 500 guests', 'Seating planner', 'Gift tracking', 'Photo gallery'],
            ],
            [
                'name' => 'Signature',
                'description' => 'Full-service digital wedding suite with announcements and premium templates.',
                'price' => 499,
                'currency' => 'USD',
                'features' => ['Unlimited designs', 'Unlimited guests', 'All modules', 'Email + SMS announcements', 'Priority support'],
            ],
        ];

        foreach ($packages as $package) {
            Package::query()->updateOrCreate(['name' => $package['name']], $package);
        }

        $templates = [
            [
                'slug' => 'classic-rose',
                'name' => 'Classic Rose',
                'config' => ['primary_color' => '#b76e79', 'font' => 'Playfair Display', 'layout' => 'classic'],
            ],
            [
                'slug' => 'golden-khmer',
                'name' => 'Golden Khmer',
                'config' => ['primary_color' => '#c9a227', 'font' => 'Moul', 'layout' => 'traditional'],
            ],
            [
                'slug' => 'minimal-ivory',
                'name' => 'Minimal Ivory',
                'config' => ['primary_color' => '#f5f0e8', 'font' => 'Inter', 'layout' => 'minimal'],
            ],
            [
                'slug' => 'emerald-garden',
                'name' => 'Emerald Garden',
                'config' => ['primary_color' => '#027a48', 'font' => 'Cormorant', 'layout' => 'botanical'],
            ],
        ];

        foreach ($templates as $template) {
            InvitationTemplate::query()->updateOrCreate(['slug' => $template['slug']], $template);
        }
    }
}
