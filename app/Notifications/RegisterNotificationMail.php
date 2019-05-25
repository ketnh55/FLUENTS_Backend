<?php

namespace App\Notifications;

use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Lang;

class RegisterNotificationMail extends Notification implements ShouldQueue
{
    use Queueable;
    protected $link;
    protected $subject;
    protected $user;

    /**
     * RegisterNotificationMail constructor.
     * @param $link
     * @param $subject
     * @param User $user
     */
    public function __construct($link, $subject, User $user)
    {
        //
        $this->link = $link;
        $this->subject = $subject;
        $this->user = $user;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {

        return (new MailMessage)
                ->subject(Lang::getFromJson($this->subject))
                ->greeting('Hi '.$this->user->username)
                ->from('contact@fluents.app', 'FLUENTS')
                ->line(Lang::getFromJson('We received a request to reset your FLUENTS password.'))
                ->action(Lang::getFromJson('Reset Password'), $this->link)
                ->line(Lang::getFromJson('This password reset link will expire in :count minutes.', ['count' => config('auth.passwords.users.expire')]))
                ->line(Lang::getFromJson('If you ignore this message, your password will not be changed. If you didn\'t request a password reset, let us know.'))
                ->line('contact@fluents.app');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
