<?php

namespace App\Notifications\Admin;

use App\Enums\Queue;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent on every successful admin panel sign-in, so a sign-in the admin
 * didn't make is noticed straight away.
 */
class AdminSignedInNotification extends Notification implements ShouldQueue
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
            ->subject('New admin panel sign-in — '.config('app.name'))
            ->line('Your admin account was just signed in to.')
            ->line('Time: '.$this->signedInAt->toDayDateTimeString().' (UTC)')
            ->line('IP address: '.$this->ipAddress)
            ->line('Browser: '.($this->userAgent !== '' ? $this->userAgent : 'unknown'))
            ->line('If this wasn\'t you, change your password and reset two-factor authentication immediately.');
    }
}
