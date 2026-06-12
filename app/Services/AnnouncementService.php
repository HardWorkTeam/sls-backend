<?php

namespace App\Services;

use App\Enums\AnnouncementChannel;
use App\Enums\AnnouncementStatus;
use App\Models\Announcement;
use App\Models\User;
use App\Models\Wedding;
use App\Repositories\AnnouncementRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Mail;
use Throwable;

class AnnouncementService
{
    public function __construct(private readonly AnnouncementRepository $announcements) {}

    public function list(Wedding $wedding, int $perPage = 15): LengthAwarePaginator
    {
        return $this->announcements->forWedding($wedding, $perPage);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Wedding $wedding, User $user, array $attributes): Announcement
    {
        $attributes['wedding_id'] = $wedding->id;
        $attributes['created_by_user_id'] = $user->id;
        $attributes['status'] = isset($attributes['scheduled_at'])
            ? AnnouncementStatus::Scheduled->value
            : AnnouncementStatus::Draft->value;

        /** @var Announcement $announcement */
        $announcement = $this->announcements->create($attributes);

        return $announcement->load('createdBy');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Announcement $announcement, array $attributes): Announcement
    {
        $this->announcements->update($announcement, $attributes);

        return $announcement->load('createdBy');
    }

    public function delete(Announcement $announcement): void
    {
        $this->announcements->delete($announcement);
    }

    /**
     * Dispatch the announcement on its channel and log each delivery.
     *
     * Email goes through the configured mailer. SMS is "ready": logs are
     * written with status queued so a provider integration can pick them
     * up. In-app notifications are stored for wedding member users.
     */
    public function send(Announcement $announcement): Announcement
    {
        $announcement->update(['status' => AnnouncementStatus::Sending->value]);

        /** @var Wedding $wedding */
        $wedding = $announcement->wedding;
        $channel = AnnouncementChannel::from($announcement->channel);

        try {
            match ($channel) {
                AnnouncementChannel::Email => $this->sendEmails($announcement, $wedding),
                AnnouncementChannel::Sms => $this->queueSms($announcement, $wedding),
                AnnouncementChannel::InApp => $this->storeInApp($announcement, $wedding),
            };

            $announcement->update([
                'status' => AnnouncementStatus::Sent->value,
                'sent_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $announcement->update(['status' => AnnouncementStatus::Failed->value]);

            throw $exception;
        }

        return $announcement->loadCount('notificationLogs');
    }

    private function sendEmails(Announcement $announcement, Wedding $wedding): void
    {
        $guests = $wedding->guests()->whereNotNull('email')->get();

        foreach ($guests as $guest) {
            $log = $announcement->notificationLogs()->create([
                'recipient_guest_id' => $guest->id,
                'channel' => AnnouncementChannel::Email->value,
                'status' => 'queued',
            ]);

            try {
                Mail::raw($announcement->body, function ($message) use ($guest, $announcement) {
                    $message->to($guest->email, $guest->name)->subject($announcement->title);
                });

                $log->update(['status' => 'sent', 'sent_at' => now()]);
            } catch (Throwable $exception) {
                $log->update(['status' => 'failed', 'error_message' => $exception->getMessage()]);
            }
        }
    }

    private function queueSms(Announcement $announcement, Wedding $wedding): void
    {
        $guests = $wedding->guests()->whereNotNull('phone')->get();

        foreach ($guests as $guest) {
            $announcement->notificationLogs()->create([
                'recipient_guest_id' => $guest->id,
                'channel' => AnnouncementChannel::Sms->value,
                'status' => 'queued',
            ]);
        }
    }

    private function storeInApp(Announcement $announcement, Wedding $wedding): void
    {
        $userIds = $wedding->members()->pluck('user_id');

        foreach ($userIds as $userId) {
            $announcement->notificationLogs()->create([
                'recipient_user_id' => $userId,
                'channel' => AnnouncementChannel::InApp->value,
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        }
    }
}
