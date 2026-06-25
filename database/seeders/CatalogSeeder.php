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
                'capabilities' => [
                    'modules' => ['seating' => false, 'gallery' => false, 'gifts' => false],
                    'guest_limit' => 150,
                    'invitation_design_limit' => 1,
                ],
            ],
            [
                'name' => 'Premium',
                'description' => 'Everything in Essential plus seating plans, gift tracking and gallery.',
                'price' => 249,
                'currency' => 'USD',
                'features' => ['3 invitation designs', 'Up to 500 guests', 'Seating planner', 'Gift tracking', 'Photo gallery'],
                'capabilities' => [
                    'modules' => ['seating' => true, 'gallery' => true, 'gifts' => true],
                    'guest_limit' => 500,
                    'invitation_design_limit' => 3,
                ],
            ],
            [
                'name' => 'Signature',
                'description' => 'Full-service digital wedding suite with premium templates.',
                'price' => 499,
                'currency' => 'USD',
                'features' => ['Unlimited designs', 'Unlimited guests', 'All modules', 'Priority support'],
                'capabilities' => [
                    'modules' => ['seating' => true, 'gallery' => true, 'gifts' => true],
                    'guest_limit' => null,
                    'invitation_design_limit' => null,
                ],
            ],
        ];

        foreach ($packages as $package) {
            Package::query()->updateOrCreate(['name' => $package['name']], $package);
        }

        $templates = [
            [
                'slug' => 'royal-khmer-v1',
                'name' => 'Royal Khmer',
                'config' => ['primary_color' => '#C9A84C', 'font' => 'Moul', 'layout' => 'traditional'],
            ],
            [
                'slug' => 'angkor-heritage-v1',
                'name' => 'Angkor Heritage',
                'config' => ['primary_color' => '#D4A020', 'font' => 'Moul', 'layout' => 'heritage'],
            ],
            [
                'slug' => 'blue-botanical-v1',
                'name' => 'Blue Botanical',
                'config' => ['primary_color' => '#6A8CB2', 'font' => 'Cormorant Garamond', 'layout' => 'botanical'],
            ],
            [
                'slug' => 'phanaroth-luxury-v1',
                'name' => 'Phanaroth Luxury',
                'config' => ['primary_color' => '#E8C97A', 'font' => 'Playfair Display', 'layout' => 'luxury'],
            ],
            [
                'slug' => 'butterfly-editorial-v1',
                'name' => 'Butterfly Editorial',
                'config' => ['primary_color' => '#C5A059', 'font' => 'Cinzel', 'layout' => 'editorial'],
            ],
            [
                'slug' => 'emerald-elegance-v1',
                'name' => 'Emerald Elegance',
                'config' => ['primary_color' => '#C9A84C', 'font' => 'Cormorant Garamond', 'layout' => 'emerald'],
            ],
        ];

        foreach ($templates as $template) {
            InvitationTemplate::query()->updateOrCreate(['slug' => $template['slug']], $template);
        }
    }
}
