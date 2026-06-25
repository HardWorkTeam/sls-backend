<?php

namespace Database\Seeders;

use App\Enums\GiftType;
use App\Enums\GuestGroupType;
use App\Enums\InvitationStatus;
use App\Enums\MemberRole;
use App\Enums\RoleKey;
use App\Enums\RsvpStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\TimelineCategory;
use App\Enums\WeddingStatus;
use App\Models\InvitationTemplate;
use App\Models\Package;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Wedding;
use Illuminate\Database\Seeder;

class DemoWeddingSeeder extends Seeder
{
    public function run(): void
    {
        $admin = $this->user('Srolanh Admin', 'admin@srolanh.com', RoleKey::SuperAdmin);
        $organizer = $this->user('Dara Organizer', 'organizer@srolanh.com', RoleKey::Organizer);
        $bride = $this->user('Sophea Chan', 'sophea@srolanh.com', RoleKey::Couple);
        $groom = $this->user('Visal Kim', 'visal@srolanh.com', RoleKey::Couple);

        $wedding = Wedding::query()->updateOrCreate(
            ['wedding_code' => 'WED-DEMO01'],
            [
                'wedding_name' => 'Sophea & Visal',
                'bride_name' => 'Sophea Chan',
                'groom_name' => 'Visal Kim',
                'phone' => '+855 12 345 678',
                'email' => 'sophea.visal@example.com',
                'wedding_date' => now()->addDays(45)->toDateString(),
                'wedding_time' => '17:30',
                'ceremony_venue' => 'Wat Botum Park Hall, Phnom Penh',
                'reception_venue' => 'Sokha Hotel Grand Ballroom',
                'google_map_link' => 'https://maps.google.com/?q=Sokha+Hotel+Phnom+Penh',
                'story_description' => 'Sophea and Visal met at university in Phnom Penh. Seven years, two cities and countless noodle bowls later, they are getting married surrounded by the people they love.',
                'status' => WeddingStatus::Published->value,
                'published_at' => now()->subDays(20),
                'package_id' => Package::query()->where('name', 'Premium')->value('id'),
                'created_by_user_id' => $organizer->id,
            ],
        );

        $wedding->members()->updateOrCreate(
            ['user_id' => $bride->id],
            ['member_role' => MemberRole::Bride->value, 'is_primary' => true],
        );
        $wedding->members()->updateOrCreate(
            ['user_id' => $groom->id],
            ['member_role' => MemberRole::Groom->value, 'is_primary' => false],
        );

        $invitation = $wedding->invitations()->updateOrCreate(
            ['invitation_code' => 'DEMO2026'],
            [
                'invitation_template_id' => InvitationTemplate::query()->where('slug', 'royal-khmer-v1')->value('id'),
                'title' => 'You are invited to our wedding',
                'status' => InvitationStatus::Published->value,
                'published_at' => now()->subDays(18),
                'settings' => [
                    'sections' => [
                        'Cover' => true, 'CoupleInfo' => true, 'LoveStory' => true, 'Schedule' => true,
                        'Gallery' => true, 'Location' => true, 'GiftRegistry' => true, 'RSVP' => true,
                    ],
                    'invitation_text_kh' => 'មានកិត្តិយសសូមគោរពអញ្ជើញ ចូលរួមជាភ្ញៀវកិត្តិយស',
                    'invitation_text_en' => 'CORDIALLY REQUEST THE HONOR OF YOUR PRESENCE',
                    'gallery_urls' => [],
                    'bank_account' => [
                        'bank' => 'ABA Bank',
                        'name' => 'Sophea Chan',
                        'number' => '000 123 456',
                        'qr_url' => '',
                    ],
                    'couple_extended' => [
                        'groom' => [
                            'nameKh' => 'វិសាល គីម', 'nameEn' => 'Visal Kim', 'photo' => '',
                            'father' => 'គីម សុវណ្ណ', 'fatherEn' => 'Kim Sovann',
                            'mother' => 'សុខ ច័ន្ទថា', 'motherEn' => 'Sok Chantha',
                        ],
                        'bride' => [
                            'nameKh' => 'សុភា ចាន់', 'nameEn' => 'Sophea Chan', 'photo' => '',
                            'father' => 'ចាន់ ដារ៉ា', 'fatherEn' => 'Chan Dara',
                            'mother' => 'លឹម ស្រីមុំ', 'motherEn' => 'Lim Sreymom',
                        ],
                    ],
                ],
            ],
        );

        $groups = collect([
            ['name' => 'Family', 'type' => GuestGroupType::Family->value, 'sort_order' => 1],
            ['name' => 'Friends', 'type' => GuestGroupType::Friends->value, 'sort_order' => 2],
            ['name' => 'VIP', 'type' => GuestGroupType::Vip->value, 'sort_order' => 3],
            ['name' => 'Company', 'type' => GuestGroupType::Company->value, 'sort_order' => 4],
        ])->mapWithKeys(function (array $group) use ($wedding) {
            return [$group['name'] => $wedding->guestGroups()->updateOrCreate(['name' => $group['name']], $group)];
        });

        $guestRows = [
            ['Chenda Sok', 'Family', '+855 11 111 111', true],
            ['Bopha Lim', 'Family', '+855 11 222 222', false],
            ['Rithy Heng', 'Friends', '+855 11 333 333', false],
            ['Sreyneang Pich', 'Friends', '+855 11 444 444', false],
            ['Samnang Oun', 'VIP', '+855 11 555 555', true],
            ['Kunthea Mao', 'Company', '+855 11 666 666', false],
            ['Vibol Tan', 'Friends', '+855 11 777 777', false],
            ['Malis Chea', 'Family', '+855 11 888 888', false],
        ];

        $guests = [];
        foreach ($guestRows as [$name, $groupName, $phone, $isVip]) {
            $guests[$name] = $wedding->guests()->updateOrCreate(
                ['name' => $name],
                [
                    'phone' => $phone,
                    'email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
                    'guest_group_id' => $groups[$groupName]->id,
                    'invitation_id' => $invitation->id,
                    'is_vip' => $isVip,
                ],
            );
        }

        $rsvpRows = [
            ['Chenda Sok', RsvpStatus::Accepted, 2, 'So happy for you both!'],
            ['Rithy Heng', RsvpStatus::Accepted, 1, 'Wouldn\'t miss it.'],
            ['Sreyneang Pich', RsvpStatus::Maybe, 1, 'Will confirm next week.'],
            ['Kunthea Mao', RsvpStatus::Declined, 1, 'Traveling that week, sorry!'],
        ];

        foreach ($rsvpRows as $index => [$guestName, $status, $count, $message]) {
            $guest = $guests[$guestName];
            $wedding->rsvpResponses()->updateOrCreate(
                ['guest_id' => $guest->id, 'invitation_id' => $invitation->id],
                [
                    'guest_name' => $guest->name,
                    'phone' => $guest->phone,
                    'number_of_guests' => $count,
                    'message' => $message,
                    'status' => $status->value,
                    'responded_at' => now()->subDays(10 - $index * 2),
                ],
            );
        }

        $tables = [];
        foreach ([['Family Table', 1, 10], ['Friends Table', 2, 10], ['VIP Table', 3, 8]] as [$name, $number, $capacity]) {
            $tables[$name] = $wedding->tables()->updateOrCreate(
                ['table_name' => $name],
                ['table_number' => $number, 'capacity' => $capacity],
            );
        }

        $seatPlan = [
            ['Chenda Sok', 'Family Table', 1],
            ['Bopha Lim', 'Family Table', 2],
            ['Malis Chea', 'Family Table', 3],
            ['Rithy Heng', 'Friends Table', 1],
            ['Samnang Oun', 'VIP Table', 1],
        ];

        foreach ($seatPlan as [$guestName, $tableName, $seat]) {
            $wedding->guests()->where('name', $guestName)->first()?->seating()->updateOrCreate(
                ['wedding_id' => $wedding->id],
                ['wedding_table_id' => $tables[$tableName]->id, 'seat_number' => $seat],
            );
        }

        $giftRows = [
            ['Chenda Sok', GiftType::Cash, 200, null],
            ['Samnang Oun', GiftType::BankTransfer, 500, null],
            ['Rithy Heng', GiftType::Item, null, 'Rice cooker'],
        ];

        foreach ($giftRows as [$guestName, $type, $amount, $item]) {
            $wedding->gifts()->updateOrCreate(
                ['guest_id' => $guests[$guestName]->id, 'gift_type' => $type->value],
                ['amount' => $amount, 'item_name' => $item, 'received_at' => now()->subDays(3)],
            );
        }

        $timelineRows = [
            [TimelineCategory::Engagement, 'Engagement Ceremony', 'Traditional Khmer engagement at the bride\'s family home.', now()->addDays(44)->setTime(8, 0), 'Bride\'s family home', 1],
            [TimelineCategory::Ceremony, 'Wedding Ceremony', 'Monks\' blessing and traditional ceremonies.', now()->addDays(45)->setTime(7, 30), 'Wat Botum Park Hall', 2],
            [TimelineCategory::Reception, 'Reception Dinner', 'Dinner, toasts and dancing.', now()->addDays(45)->setTime(17, 30), 'Sokha Hotel Grand Ballroom', 3],
            [TimelineCategory::AfterParty, 'After Party', 'Live band and late-night snacks.', now()->addDays(45)->setTime(21, 30), 'Sokha Hotel Sky Bar', 4],
        ];

        foreach ($timelineRows as [$category, $title, $description, $startsAt, $location, $order]) {
            $wedding->timelineEvents()->updateOrCreate(
                ['title' => $title],
                [
                    'category' => $category->value,
                    'description' => $description,
                    'starts_at' => $startsAt,
                    'location' => $location,
                    'sort_order' => $order,
                    'is_public' => true,
                ],
            );
        }

        $wedding->albums()->updateOrCreate(
            ['name' => 'Pre-Wedding Shoot'],
            ['description' => 'Photos from the couple\'s pre-wedding session.', 'is_public' => true],
        );

        // Demo wedding has SUBMITTED payment awaiting admin confirmation — gives
        // the admin /payments Confirm flow and the couple "awaiting" state something
        // real to show.
        Subscription::query()->updateOrCreate(
            ['wedding_id' => $wedding->id],
            [
                'package_id' => $wedding->package_id,
                'amount' => Package::query()->whereKey($wedding->package_id)->value('price'),
                'currency' => 'USD',
                'status' => SubscriptionStatus::Submitted->value,
                'payment_method' => 'aba',
                'payment_reference' => 'DEMO-TXN-0001',
                'submitted_at' => now()->subDays(2),
                'paid_at' => null,
            ],
        );

        $this->seedPlatformData($organizer);
    }

    /**
     * Generate a spread of registered users, weddings, invitations and paid
     * subscriptions over the last 30 days so the super-admin Platform Analytics
     * dashboard (revenue trend, system growth, package sales, template usage)
     * renders against realistic data.
     */
    private function seedPlatformData(User $organizer): void
    {
        // Weight package selection so sales rank Signature > Premium > Essential
        // (matching the analytics mockup); revenue follows from package price.
        $packages = Package::query()->pluck('id', 'name');
        $packagePlan = array_merge(
            array_fill(0, 16, 'Signature'),
            array_fill(0, 10, 'Premium'),
            array_fill(0, 6, 'Essential'),
        );

        $templateIds = InvitationTemplate::query()->pluck('id')->all();
        $coupleRoleId = Role::query()->where('key', RoleKey::Couple->value)->value('id');

        // Drop the old "guest-user" demo accounts — registered users are couples,
        // and guests are display-only records that never have an account.
        User::query()->where('email', 'like', 'guest-user-%@srolanh.com')->each(function (User $stale) {
            $stale->roles()->detach();
            $stale->delete();
        });

        // Extra registered couples spread across the last 30 days (system growth).
        for ($i = 1; $i <= 40; $i++) {
            $user = User::query()->updateOrCreate(
                ['email' => "couple-user-{$i}@srolanh.com"],
                ['name' => "Couple User {$i}", 'password' => 'password', 'is_active' => true],
            );
            $user->roles()->syncWithoutDetaching([$coupleRoleId]);
            $user->forceFill(['created_at' => now()->subDays(rand(0, 29))->setTime(rand(8, 20), rand(0, 59))])->save();
        }

        foreach ($packagePlan as $index => $packageName) {
            $n = $index + 1;
            $packageId = $packages[$packageName];
            $price = Package::query()->whereKey($packageId)->value('price');
            $paidAt = now()->subDays(rand(0, 29))->setTime(rand(8, 20), rand(0, 59));

            $w = Wedding::query()->updateOrCreate(
                ['wedding_code' => sprintf('WED-SEED%03d', $n)],
                [
                    'wedding_name' => "Demo Couple {$n}",
                    'bride_name' => "Bride {$n}",
                    'groom_name' => "Groom {$n}",
                    'wedding_date' => now()->addDays(rand(10, 120))->toDateString(),
                    'status' => WeddingStatus::Published->value,
                    'published_at' => $paidAt,
                    'package_id' => $packageId,
                    'created_by_user_id' => $organizer->id,
                ],
            );

            $w->invitations()->updateOrCreate(
                ['invitation_code' => sprintf('SEED%03d', $n)],
                [
                    'invitation_template_id' => $templateIds[$index % count($templateIds)],
                    'title' => 'You are invited',
                    'status' => InvitationStatus::Published->value,
                    'published_at' => $paidAt,
                ],
            );

            // Vary status: most paid (revenue), a few submitted (awaiting admin),
            // a few pending (package picked, not yet paid).
            if ($n % 8 === 0) {
                $status = SubscriptionStatus::Submitted->value;
                $submittedAt = $paidAt;
                $confirmedAt = null;
            } elseif ($n % 8 === 1) {
                $status = SubscriptionStatus::Pending->value;
                $submittedAt = null;
                $confirmedAt = null;
            } else {
                $status = SubscriptionStatus::Paid->value;
                $submittedAt = $paidAt->copy()->subDay();
                $confirmedAt = $paidAt;
            }

            Subscription::query()->updateOrCreate(
                ['wedding_id' => $w->id],
                [
                    'package_id' => $packageId,
                    'amount' => $price,
                    'currency' => 'USD',
                    'status' => $status,
                    'payment_method' => $status === SubscriptionStatus::Pending->value ? null : 'khqr',
                    'payment_reference' => $status === SubscriptionStatus::Pending->value ? null : sprintf('SEED-TXN-%04d', $n),
                    'submitted_at' => $submittedAt,
                    'paid_at' => $confirmedAt,
                ],
            );
        }
    }

    private function user(string $name, string $email, RoleKey $roleKey): User
    {
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => 'password', 'is_active' => true],
        );

        $roleId = Role::query()->where('key', $roleKey->value)->value('id');
        $user->roles()->syncWithoutDetaching([$roleId]);

        return $user;
    }
}
