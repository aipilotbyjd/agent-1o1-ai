<?php

namespace App\Notifications\SuperAdmin;

use App\Enums\Queue;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent on every successful super admin panel sign-in, so a sign-in the super admin
 * didn't make is noticed straight away.
 */
class SuperAdminSignedInNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $ipAddress,
        public readonly string $userAgent,
        public readonly CarbonImmutable $signedInAt,
    ) {
        $this->onQueue(Queue::Notification->value);
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New super admin panel sign-in — '.config('app.name'))
            ->line('Your super admin account was just signed in to.')
            ->line('Time: '.$this->signedInAt->toDayDateTimeString().' (UTC)')
            ->line('IP address: '.$this->ipAddress)
            ->line('Browser: '.($this->userAgent !== '' ? $this->userAgent : 'unknown'))
            ->line('If this wasn\'t you, change your password and reset two-factor authentication immediately.');
    }
}
