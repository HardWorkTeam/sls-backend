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

        $this->seedPlatformData();
    }

    /**
     * Seed a realistic spread of Cambodian couples, their accounts, invitations
     * and subscriptions across the last 30 days so the super-admin Platform
     * Analytics dashboard (revenue trend, system growth, package sales, template
     * usage) renders against believable data rather than obvious placeholders.
     */
    private function seedPlatformData(): void
    {
        $packages = Package::query()->pluck('id', 'name');
        $packagePrice = Package::query()->pluck('price', 'name');
        $templateIds = InvitationTemplate::query()->pluck('id', 'slug');
        $coupleRoleId = Role::query()->where('key', RoleKey::Couple->value)->value('id');

        // Clear out the old obviously-fake "Demo Couple N" / generic accounts left
        // over from earlier seeds so the platform looks like genuine registrations.
        Wedding::query()->where('wedding_code', 'like', 'WED-SEED%')->forceDelete();
        User::query()
            ->where('email', 'like', 'couple-user-%@srolanh.com')
            ->orWhere('email', 'like', 'guest-user-%@srolanh.com')
            ->each(function (User $stale) {
                $stale->roles()->detach();
                $stale->delete();
            });

        // [bride, groom, ceremony venue, city, package, template slug, status, days ago]
        $couples = [
            ['Sreymom Chan', 'Dara Kim', 'Sokha Phnom Penh Hotel', 'Phnom Penh', 'Signature', 'royal-khmer-v1', 'paid', 2],
            ['Channary Sok', 'Vichea Heng', 'Raffles Hotel Le Royal', 'Phnom Penh', 'Signature', 'angkor-heritage-v1', 'paid', 3],
            ['Bopha Lim', 'Rithy Pich', 'NagaWorld Grand Ballroom', 'Phnom Penh', 'Premium', 'royal-khmer-v1', 'paid', 4],
            ['Kunthea Oun', 'Sovann Mao', 'Sofitel Phokeethra', 'Phnom Penh', 'Signature', 'emerald-elegance-v1', 'paid', 5],
            ['Theary Tan', 'Chamroeun Chea', 'Hyatt Regency Phnom Penh', 'Phnom Penh', 'Premium', 'blue-botanical-v1', 'paid', 6],
            ['Sreypov Sam', 'Kosal Ros', 'Koh Pich Convention Centre', 'Phnom Penh', 'Essential', 'royal-khmer-v1', 'paid', 7],
            ['Chenda Nuon', 'Piseth Ky', 'Rosewood Phnom Penh', 'Phnom Penh', 'Signature', 'red-rose-luxury-v1', 'paid', 8],
            ['Mealea Em', 'Veasna Meas', 'Hotel Cambodiana', 'Phnom Penh', 'Premium', 'butterfly-editorial-v1', 'paid', 9],
            ['Phalla Hor', 'Sambath Yim', 'Himawari Hotel', 'Phnom Penh', 'Essential', 'angkor-heritage-v1', 'submitted', 1],
            ['Raksmey Long', 'Makara Ung', 'Borey Peng Huoth Ballroom', 'Phnom Penh', 'Premium', 'royal-khmer-v1', 'paid', 11],
            ['Nary Khorn', 'Channarith Seng', 'Olympia City Hall', 'Phnom Penh', 'Essential', 'emerald-elegance-v1', 'paid', 12],
            ['Leakhena Yon', 'Visoth Run', 'Chaktomuk Conference Hall', 'Phnom Penh', 'Signature', 'red-rose-luxury-v1', 'paid', 13],
            ['Dalin Chan', 'Sereyvuth Kim', 'Sokha Siem Reap Resort', 'Siem Reap', 'Premium', 'angkor-heritage-v1', 'paid', 14],
            ['Sreyleak Sok', 'Phearak Heng', 'Angkor Paradise Hotel', 'Siem Reap', 'Essential', 'blue-botanical-v1', 'paid', 15],
            ['Chanthavy Pich', 'Rattanak Oun', 'Wedding Palace Siem Reap', 'Siem Reap', 'Premium', 'royal-khmer-v1', 'submitted', 2],
            ['Malis Mao', 'Vibol Chea', 'Borey Peng Huoth Ballroom', 'Phnom Penh', 'Signature', 'emerald-elegance-v1', 'paid', 17],
            ['Vanna Ros', 'Pichet Sam', 'NagaWorld Grand Ballroom', 'Phnom Penh', 'Premium', 'butterfly-editorial-v1', 'paid', 18],
            ['Pisey Ky', 'Samnang Nuon', 'Phnom Penh Hotel', 'Phnom Penh', 'Essential', 'royal-khmer-v1', 'paid', 19],
            ['Sreyneang Em', 'Chanthou Meas', 'Hangneak Restaurant', 'Phnom Penh', 'Essential', 'angkor-heritage-v1', 'pending', 1],
            ['Kanha Hor', 'Borey Yim', 'Vimean Tip Restaurant', 'Phnom Penh', 'Premium', 'red-rose-luxury-v1', 'paid', 21],
            ['Sokunthea Long', 'Nimol Ung', 'Sofitel Phokeethra', 'Phnom Penh', 'Signature', 'royal-khmer-v1', 'paid', 23],
            ['Davy Khorn', 'Visal Seng', 'Rosewood Phnom Penh', 'Phnom Penh', 'Signature', 'emerald-elegance-v1', 'paid', 25],
            ['Sophea Yon', 'Sokha Run', 'Battambang Resort', 'Battambang', 'Essential', 'blue-botanical-v1', 'paid', 27],
            ['Chanlina Tan', 'Veasna Phon', 'Independence Hotel Sihanoukville', 'Sihanoukville', 'Premium', 'butterfly-editorial-v1', 'pending', 3],
        ];

        foreach ($couples as $i => [$brideName, $groomName, $venue, $city, $packageName, $templateSlug, $status, $daysAgo]) {
            $n = $i + 1;
            $createdAt = now()->subDays($daysAgo)->setTime(9 + ($i % 10), ($i * 7) % 60);
            $packageId = $packages[$packageName];

            // The couple's primary contact registers the account.
            [$brideGiven, $brideFamily] = array_pad(explode(' ', $brideName, 2), 2, '');
            $email = strtolower($brideGiven.'.'.$brideFamily).$n.'@gmail.com';
            $user = User::query()->updateOrCreate(
                ['email' => $email],
                ['name' => $brideName, 'password' => 'password', 'is_active' => true],
            );
            $user->roles()->syncWithoutDetaching([$coupleRoleId]);
            $user->forceFill(['created_at' => $createdAt->copy()->subDays(3)])->save();

            $wedding = Wedding::query()->updateOrCreate(
                ['wedding_code' => sprintf('WED-%s%03d', strtoupper(substr($brideGiven, 0, 2)), $n)],
                [
                    'wedding_name' => "{$brideGiven} & ".explode(' ', $groomName)[0],
                    'bride_name' => $brideName,
                    'groom_name' => $groomName,
                    'phone' => sprintf('+855 %02d %03d %03d', rand(10, 99), rand(100, 999), rand(100, 999)),
                    'email' => $email,
                    'wedding_date' => now()->addDays(21 + $i * 4)->toDateString(),
                    'wedding_time' => ['16:00', '16:30', '17:00', '17:30', '18:00'][$i % 5],
                    'ceremony_venue' => $venue,
                    'reception_venue' => $venue.', Grand Ballroom',
                    'google_map_link' => 'https://maps.google.com/?q='.urlencode($venue.' '.$city),
                    'status' => WeddingStatus::Published->value,
                    'published_at' => $createdAt,
                    'package_id' => $packageId,
                    'created_by_user_id' => $user->id,
                ],
            );

            $wedding->invitations()->updateOrCreate(
                ['invitation_code' => sprintf('INV%s%03d', strtoupper(substr($brideGiven, 0, 2)), $n)],
                [
                    'invitation_template_id' => $templateIds[$templateSlug],
                    'title' => 'You are invited to our wedding',
                    'status' => InvitationStatus::Published->value,
                    'published_at' => $createdAt,
                ],
            );

            $subStatus = match ($status) {
                'paid' => SubscriptionStatus::Paid->value,
                'submitted' => SubscriptionStatus::Submitted->value,
                default => SubscriptionStatus::Pending->value,
            };

            Subscription::query()->updateOrCreate(
                ['wedding_id' => $wedding->id],
                [
                    'package_id' => $packageId,
                    'amount' => $packagePrice[$packageName],
                    'currency' => 'USD',
                    'status' => $subStatus,
                    'payment_method' => $status === 'pending' ? null : (['aba', 'khqr', 'wing'][$i % 3]),
                    'payment_reference' => $status === 'pending' ? null : sprintf('TXN-%s-%04d', strtoupper(substr($brideGiven, 0, 3)), $n),
                    'submitted_at' => $status === 'pending' ? null : $createdAt->copy()->subDay(),
                    'paid_at' => $status === 'paid' ? $createdAt : null,
                ],
            );
        }

        // A handful of couples who registered but haven't created a wedding yet —
        // real platforms always have more sign-ups than active events.
        $browsers = [
            ['Sothea Pen', 5], ['Chanda Vong', 8], ['Reaksa Ngin', 10], ['Sovannarith Lor', 13],
            ['Sokleap Touch', 16], ['Veronica Sruoch', 19], ['Dymey Chhun', 22], ['Pheakdey Hak', 26],
        ];
        foreach ($browsers as $i => [$name, $daysAgo]) {
            [$given, $family] = array_pad(explode(' ', $name, 2), 2, '');
            $user = User::query()->updateOrCreate(
                ['email' => strtolower($given.'.'.$family).'@gmail.com'],
                ['name' => $name, 'password' => 'password', 'is_active' => true],
            );
            $user->roles()->syncWithoutDetaching([$coupleRoleId]);
            $user->forceFill(['created_at' => now()->subDays($daysAgo)->setTime(10 + $i, ($i * 11) % 60)])->save();
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
