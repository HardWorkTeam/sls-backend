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
use App\Enums\ExpenseStatus;
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
            ['Lok Oknha Chan Dara', 'Family', '+855 12 999 001', 'dara.chan@example.com', 'Villa 45A, St 310, Boeung Keng Kang, Phnom Penh', 'Father of the Bride', true],
            ['Lok Chumteav Lim Sreymom', 'Family', '+855 12 999 002', 'sreymom.lim@example.com', 'Villa 45A, St 310, Boeung Keng Kang, Phnom Penh', 'Mother of the Bride', true],
            ['Mr. Kim Sovann', 'Family', '+855 11 888 001', 'sovann.kim@example.com', 'House 12, St 598, Toul Kork, Phnom Penh', 'Father of the Groom', true],
            ['Mrs. Sok Chantha', 'Family', '+855 11 888 002', 'chantha.sok@example.com', 'House 12, St 598, Toul Kork, Phnom Penh', 'Mother of the Groom', true],
            ['Chan Rotha', 'Family', '+855 77 111 222', 'rotha.chan@example.com', 'Phnom Penh', 'Brother of the Bride', false],
            ['Kim Sreypich', 'Family', '+855 88 333 444', 'sreypich.kim@example.com', 'Phnom Penh', 'Sister of the Groom', false],
            ['H.E. Keo Puthy', 'VIP', '+855 12 222 333', 'puthy.keo@gov.kh', 'Phnom Penh', 'VIP Guest from Ministry', true],
            ['Dr. Seng Sarin', 'VIP', '+855 12 555 666', 'sarin.seng@example.com', 'Phnom Penh', 'Family Doctor', true],
            ['Madam Oung Sopheap', 'VIP', '+855 17 777 888', 'sopheap.oung@example.com', 'Phnom Penh', 'Close family friend', true],
            ['Rithy Heng', 'Friends', '+855 93 111 222', 'rithy.heng@example.com', 'Phnom Penh', 'Best Man / Groomsman', false],
            ['Sreyneang Pich', 'Friends', '+855 95 333 444', 'sreyneang.pich@example.com', 'Phnom Penh', 'Maid of Honor / Bridesmaid', false],
            ['Chenda Sok', 'Friends', '+855 98 555 666', 'chenda.sok@example.com', 'Phnom Penh', 'MC for the Reception dinner', false],
            ['Malis Chea', 'Friends', '+855 70 777 888', 'malis.chea@example.com', 'Siem Reap', 'High school friend traveling from Siem Reap', false],
            ['Sokha Pen', 'Friends', '+855 15 999 111', 'sokha.pen@example.com', 'Phnom Penh', 'University classmate', false],
            ['Chan Chhaya', 'Friends', '+855 16 222 333', 'chhaya.chan@example.com', 'Phnom Penh', 'Photographer friend', false],
            ['Phirun Seng', 'Friends', '+855 92 444 555', 'phirun.seng@example.com', 'Phnom Penh', 'Groom\'s soccer club friend', false],
            ['Kunthea Mao', 'Company', '+855 12 444 888', 'kunthea.mao@company.com', 'Phnom Penh', 'Bride\'s Manager at Work', false],
            ['Vibol Tan', 'Company', '+855 87 555 999', 'vibol.tan@company.com', 'Phnom Penh', 'Bride\'s colleague (Developer)', false],
            ['Samnang Oun', 'Company', '+855 96 333 222', 'samnang.oun@company.com', 'Phnom Penh', 'Bride\'s colleague (HR Director)', false],
            ['Tep Sovann', 'Company', '+855 99 777 666', 'sovann.tep@company.com', 'Phnom Penh', 'Groom\'s colleague (Designer)', false],
            ['Nguon Rith', 'Company', '+855 78 888 999', 'rith.nguon@company.com', 'Phnom Penh', 'Groom\'s colleague (QA)', false],
        ];

        $guests = [];
        foreach ($guestRows as [$name, $groupName, $phone, $email, $address, $note, $isVip]) {
            $guests[$name] = $wedding->guests()->updateOrCreate(
                ['name' => $name],
                [
                    'phone' => $phone,
                    'email' => $email,
                    'address' => $address,
                    'note' => $note,
                    'guest_group_id' => $groups[$groupName]->id,
                    'invitation_id' => $invitation->id,
                    'is_vip' => $isVip,
                ],
            );
        }

        $rsvpRows = [
            ['Lok Oknha Chan Dara', RsvpStatus::Accepted, 2, 'Looking forward to our family\'s big day!'],
            ['Mr. Kim Sovann', RsvpStatus::Accepted, 2, 'Can\'t wait to celebrate!'],
            ['H.E. Keo Puthy', RsvpStatus::Accepted, 4, 'Honored to attend your wedding, blessing the couple.'],
            ['Rithy Heng', RsvpStatus::Accepted, 1, 'Best man is ready! See you there!'],
            ['Sreyneang Pich', RsvpStatus::Accepted, 1, 'So excited for you, Sophea! Sending love.'],
            ['Chenda Sok', RsvpStatus::Accepted, 1, 'Honored to host the stage as MC.'],
            ['Kunthea Mao', RsvpStatus::Accepted, 2, 'Congratulations to the lovely couple!'],
            ['Vibol Tan', RsvpStatus::Declined, 1, 'Sorry, I will be abroad on a business trip.'],
            ['Samnang Oun', RsvpStatus::Maybe, 2, 'Will check schedule and confirm by next week.'],
            ['Dr. Seng Sarin', RsvpStatus::Accepted, 2, 'Will attend the evening reception.'],
            ['Malis Chea', RsvpStatus::Accepted, 1, 'Wouldn\'t miss it for the world, coming from Siem Reap!'],
            ['Chan Chhaya', RsvpStatus::Accepted, 2, 'Happy for you both!'],
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
                    'responded_at' => now()->subDays(12 - $index),
                ],
            );
        }

        $tables = [];
        foreach ([
            ['VIP Table', 1, 10],
            ['Bride Family Table', 2, 10],
            ['Groom Family Table', 3, 10],
            ['Friends Table', 4, 10],
            ['Company Table', 5, 10]
        ] as [$name, $number, $capacity]) {
            $tables[$name] = $wedding->tables()->updateOrCreate(
                ['table_name' => $name],
                ['table_number' => $number, 'capacity' => $capacity],
            );
        }

        $seatPlan = [
            ['Lok Oknha Chan Dara', 'VIP Table', 1],
            ['Lok Chumteav Lim Sreymom', 'VIP Table', 2],
            ['Mr. Kim Sovann', 'VIP Table', 3],
            ['Mrs. Sok Chantha', 'VIP Table', 4],
            ['Dr. Seng Sarin', 'VIP Table', 5],
            ['H.E. Keo Puthy', 'VIP Table', 6],
            ['Chan Rotha', 'Bride Family Table', 1],
            ['Kim Sreypich', 'Groom Family Table', 1],
            ['Rithy Heng', 'Friends Table', 1],
            ['Sreyneang Pich', 'Friends Table', 2],
            ['Chenda Sok', 'Friends Table', 3],
            ['Malis Chea', 'Friends Table', 4],
            ['Kunthea Mao', 'Company Table', 1],
            ['Samnang Oun', 'Company Table', 2],
        ];

        foreach ($seatPlan as [$guestName, $tableName, $seat]) {
            $wedding->guests()->where('name', $guestName)->first()?->seating()->updateOrCreate(
                ['wedding_id' => $wedding->id],
                ['wedding_table_id' => $tables[$tableName]->id, 'seat_number' => $seat],
            );
        }

        $giftRows = [
            ['Lok Oknha Chan Dara', GiftType::Cash, 2000.00, 'USD', null, 'Wedding blessing from mom and dad'],
            ['Mr. Kim Sovann', GiftType::Cash, 1500.00, 'USD', null, 'For your new beginning together'],
            ['H.E. Keo Puthy', GiftType::Cash, 500.00, 'USD', null, 'Congratulations! Wishing you a long life together.'],
            ['Chenda Sok', GiftType::BankTransfer, 200.00, 'USD', null, 'Wishing you happiness!'],
            ['Rithy Heng', GiftType::BankTransfer, 100.00, 'USD', null, 'Congrats brother! Happy marriage!'],
            ['Kunthea Mao', GiftType::BankTransfer, 150.00, 'USD', null, 'Happy married life!'],
            ['Dr. Seng Sarin', GiftType::Cash, 800000.00, 'KHR', null, 'Wishing you love and happiness always.'],
            ['Madam Oung Sopheap', GiftType::Cash, 1200000.00, 'KHR', null, 'Big congrats to Visal and Sophea!'],
            ['Malis Chea', GiftType::BankTransfer, 400000.00, 'KHR', null, 'So happy for you guys!'],
            ['Chan Chhaya', GiftType::BankTransfer, 50.00, 'USD', null, 'Blessing to the couple!'],
            ['Sreyneang Pich', GiftType::Item, null, 'USD', 'Premium Dining Set', 'A little something for your new kitchen.'],
            ['Tep Sovann', GiftType::Item, null, 'USD', 'Nespresso Coffee Machine', 'Fuel for your early mornings!'],
        ];

        foreach ($giftRows as [$guestName, $type, $amount, $currency, $item, $note]) {
            $wedding->gifts()->updateOrCreate(
                ['guest_id' => $guests[$guestName]->id, 'gift_type' => $type->value],
                [
                    'amount' => $amount,
                    'currency' => $currency,
                    'item_name' => $item,
                    'note' => $note,
                    'received_at' => now()->subDays(3)
                ],
            );
        }

        $timelineEvents = [
            [
                TimelineCategory::Engagement,
                'Groom\'s Procession (Hai Khan Mla)',
                'Traditional parade bringing gifts from the groom\'s family to the bride\'s house.',
                now()->addDays(44)->setTime(8, 0),
                'Bride\'s Family Villa, Toul Kork',
                1
            ],
            [
                TimelineCategory::Engagement,
                'Ring Exchange & Tea Ceremony (Sien Don Ta)',
                'Paying respects to ancestors and exchanging wedding rings in front of families.',
                now()->addDays(44)->setTime(10, 0),
                'Bride\'s Family Villa, Toul Kork',
                2
            ],
            [
                TimelineCategory::Ceremony,
                'Hair Cutting Ceremony (Gat Sah)',
                'Symbolic cleansing ceremony performed by parents and guests to bring good luck.',
                now()->addDays(45)->setTime(8, 0),
                'Wat Botum Park Hall, Phnom Penh',
                3
            ],
            [
                TimelineCategory::Ceremony,
                'Monks\' Blessing (Chang Han)',
                'Buddhist monks chant blessings and sprinkle holy water on the couple for prosperity.',
                now()->addDays(45)->setTime(9, 30),
                'Wat Botum Park Hall, Phnom Penh',
                4
            ],
            [
                TimelineCategory::Reception,
                'Reception Grand Dinner',
                'Banquet celebration, speeches, cake cutting, guest dining, and live band performance.',
                now()->addDays(45)->setTime(17, 30),
                'Sokha Hotel Grand Ballroom',
                5
            ],
            [
                TimelineCategory::AfterParty,
                'After Party & Dancing',
                'DJ set, dancing, cocktails and late-night snacks with close friends.',
                now()->addDays(45)->setTime(21, 30),
                'Sokha Hotel Sky Bar',
                6
            ],
        ];

        foreach ($timelineEvents as [$category, $title, $description, $startsAt, $location, $order]) {
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

        $expenses = [
            ['Wedding Venue Booking', 'Sokha Hotel', 12000.00, 5000.00, ExpenseStatus::Partial, 'Deposit paid. Balance due 1 week before event.', now()->subDays(30), 'USD'],
            ['Catering & Drinks (60 Tables)', 'Sokha Catering', 15000.00, 0.00, ExpenseStatus::Planned, 'Estimated cost. Awaiting final guest count.', now()->addDays(10), 'USD'],
            ['Wedding Planner Services', 'Dara Organizer', 2500.00, 2500.00, ExpenseStatus::Paid, 'Paid in full.', now()->subDays(25), 'USD'],
            ['Floral & Stage Decoration', 'Romdoul Florist', 4500.00, 2000.00, ExpenseStatus::Partial, 'Deposit paid for customized stage background.', now()->subDays(15), 'USD'],
            ['Photography & Video Services', 'Khmer Wedding Studio', 3000.00, 1500.00, ExpenseStatus::Partial, 'Includes pre-wedding shoot + 2 days event coverage.', now()->subDays(20), 'USD'],
            ['Bride & Groom Custom Attire', 'Queen Boutique', 3500.00, 3500.00, ExpenseStatus::Paid, 'Tailoring completed and clothes picked up.', now()->subDays(10), 'USD'],
            ['Bridal Makeup & Styling', 'Paris Makeup', 1200.00, 600.00, ExpenseStatus::Partial, 'Paid deposit for 2-day session.', now()->subDays(12), 'USD'],
            ['Invitation Cards Printing', 'Phsar Thmey Print', 500.00, 500.00, ExpenseStatus::Paid, '600 cards printed and delivered.', now()->subDays(18), 'USD'],
            ['Live Band & Sound System', 'Classic Sound', 1800.00, 0.00, ExpenseStatus::Planned, 'Planned live orchestra + sound equipment hire.', now()->addDays(20), 'USD'],
            ['Custom Wedding Rings', 'Koh Pich Jewelry', 8000000.00, 8000000.00, ExpenseStatus::Paid, 'Gold rings with diamond setting.', now()->subDays(8), 'KHR'],
        ];

        foreach ($expenses as [$itemName, $vendor, $amount, $paidAmount, $status, $note, $spentAt, $currency]) {
            $wedding->expenses()->updateOrCreate(
                ['item_name' => $itemName],
                [
                    'vendor' => $vendor,
                    'amount' => $amount,
                    'paid_amount' => $paidAmount,
                    'status' => $status->value,
                    'note' => $note,
                    'spent_at' => $spentAt,
                    'currency' => $currency,
                ]
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
