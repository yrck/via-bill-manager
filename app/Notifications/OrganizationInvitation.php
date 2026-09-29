<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrganizationInvitation extends Notification
{
    public function __construct(public string $organization, public string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Join '.$this->organization.' on VIA')
            ->line('You have been invited to join '.$this->organization.'.')
            ->line('Sign in or create your own login using this email address. This invitation expires in seven days.')
            ->action('View invitation', route('invitations.show', $this->token))
            ->line('If you were not expecting this invitation, you can ignore it.');
    }
}
