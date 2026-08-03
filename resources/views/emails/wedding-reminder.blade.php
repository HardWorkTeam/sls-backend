@php
    use App\Enums\WeddingReminderMilestone;
    use App\Enums\WeddingStatus;

    $isWeddingDay = $milestone === WeddingReminderMilestone::WeddingDay;
    $isDraft = $wedding->status === WeddingStatus::Draft->value;

    $countdown = $isWeddingDay
        ? 'Today'
        : $daysRemaining.' '.\Illuminate\Support\Str::plural('day', $daysRemaining);

    // Inline styles only — Gmail strips <style> blocks, and every mail client
    // that matters renders table layouts consistently while flexbox is a
    // coin toss.
    $cell = 'font-family: Arial, Helvetica, sans-serif; color: #18181b;';
    $muted = 'font-family: Arial, Helvetica, sans-serif; color: #71717a; font-size: 13px;';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $milestone->subject($wedding->wedding_name) }}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f4f5;">
    {{-- Preheader: the grey preview line in the inbox list. Hidden in the body. --}}
    <div style="display: none; max-height: 0; overflow: hidden; opacity: 0;">
        {{ $milestone->headline() }} until {{ $wedding->wedding_name }}.
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f4f4f5; padding: 24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width: 100%; max-width: 600px; background-color: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e4e4e7;">

                    <!-- Header -->
                    <tr>
                        <td style="{{ $cell }} background-color: #18181b; padding: 28px 32px; text-align: center;">
                            <div style="font-size: 13px; letter-spacing: 2px; text-transform: uppercase; color: #a1a1aa;">Srolanh</div>
                            <div style="font-size: 24px; font-weight: bold; color: #ffffff; padding-top: 8px;">{{ $milestone->headline() }}</div>
                        </td>
                    </tr>

                    <!-- Countdown -->
                    <tr>
                        <td style="{{ $cell }} padding: 32px 32px 8px; text-align: center;">
                            <div style="font-size: 44px; font-weight: bold; line-height: 1.1; color: #be123c;">{{ $countdown }}</div>
                            <div style="{{ $muted }} padding-top: 6px;">
                                @if ($isWeddingDay)
                                    Congratulations — this is it.
                                @else
                                    until {{ $wedding->wedding_name }}
                                @endif
                            </div>
                        </td>
                    </tr>

                    <!-- Couple -->
                    <tr>
                        <td style="{{ $cell }} padding: 16px 32px 0; text-align: center;">
                            <div style="font-size: 20px; font-weight: bold;">{{ $wedding->bride_name }} &amp; {{ $wedding->groom_name }}</div>
                        </td>
                    </tr>

                    <!-- Details -->
                    <tr>
                        <td style="padding: 24px 32px 8px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #fafafa; border: 1px solid #e4e4e7; border-radius: 8px;">
                                <tr>
                                    <td style="{{ $muted }} padding: 14px 16px 4px;">Date</td>
                                </tr>
                                <tr>
                                    <td style="{{ $cell }} font-size: 15px; padding: 0 16px 12px;">
                                        {{ $wedding->wedding_date?->format('l, j F Y') }}@if ($time) · {{ $time }}@endif
                                    </td>
                                </tr>
                                @if ($wedding->ceremony_venue)
                                    <tr>
                                        <td style="{{ $muted }} padding: 4px 16px 4px; border-top: 1px solid #e4e4e7;">Ceremony</td>
                                    </tr>
                                    <tr>
                                        <td style="{{ $cell }} font-size: 15px; padding: 0 16px 12px;">{{ $wedding->ceremony_venue }}</td>
                                    </tr>
                                @endif
                                @if ($wedding->reception_venue)
                                    <tr>
                                        <td style="{{ $muted }} padding: 4px 16px 4px; border-top: 1px solid #e4e4e7;">Reception</td>
                                    </tr>
                                    <tr>
                                        <td style="{{ $cell }} font-size: 15px; padding: 0 16px 14px;">{{ $wedding->reception_venue }}</td>
                                    </tr>
                                @endif
                            </table>
                        </td>
                    </tr>

                    <!-- Guest numbers -->
                    @if (($stats['total_guests'] ?? 0) > 0)
                        <tr>
                            <td style="padding: 16px 32px 0;">
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <td width="33%" style="{{ $cell }} text-align: center; padding: 8px 4px;">
                                            <div style="font-size: 22px; font-weight: bold;">{{ $stats['total_guests'] }}</div>
                                            <div style="{{ $muted }}">Invited</div>
                                        </td>
                                        <td width="33%" style="{{ $cell }} text-align: center; padding: 8px 4px;">
                                            <div style="font-size: 22px; font-weight: bold; color: #15803d;">{{ $stats['accepted'] ?? 0 }}</div>
                                            <div style="{{ $muted }}">Accepted</div>
                                        </td>
                                        <td width="33%" style="{{ $cell }} text-align: center; padding: 8px 4px;">
                                            <div style="font-size: 22px; font-weight: bold; color: #b45309;">{{ $stats['awaiting'] ?? 0 }}</div>
                                            <div style="{{ $muted }}">No reply yet</div>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    @endif

                    <!-- Unpublished warning -->
                    @if ($isDraft)
                        <tr>
                            <td style="padding: 16px 32px 0;">
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #fffbeb; border: 1px solid #fde68a; border-radius: 8px;">
                                    <tr>
                                        <td style="{{ $cell }} font-size: 14px; padding: 14px 16px; color: #92400e;">
                                            <strong>Your wedding is still a draft.</strong>
                                            Guests cannot open your invitation or RSVP until you publish it from the portal.
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    @endif

                    <!-- CTA -->
                    <tr>
                        <td style="padding: 28px 32px 8px; text-align: center;">
                            <a href="{{ $portalUrl }}" style="display: inline-block; background-color: #18181b; color: #ffffff; font-family: Arial, Helvetica, sans-serif; font-size: 15px; font-weight: bold; text-decoration: none; padding: 13px 28px; border-radius: 8px;">
                                Open my wedding
                            </a>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="{{ $muted }} padding: 24px 32px 28px; text-align: center; line-height: 20px;">
                            You are receiving this because you are listed on {{ $wedding->wedding_name }} in Srolanh.<br>
                            Wedding code {{ $wedding->wedding_code }}.
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
