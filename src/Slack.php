<?php

namespace Pressutto\LaravelSlack;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\SlackMessage;
use Pressutto\LaravelSlack\Notifications\SimpleSlack;

class Slack
{
    /**
     * @var string[]
     */
    private $recipients = [];

    /**
     * @var AnonymousNotifiable
     */
    private $anonymousNotifiable;

    /**
     * @var string
     */
    private $from;

    /**
     * @var string
     */
    private $image;

    /**
     * @var array
     */
    private $config;

    public function __construct(array $config)
    {
        $this->recipients = [$config['default_channel']];
        $this->from = $config['application_name'];
        $this->image = $config['application_image'];
        $this->config = $config;
    }

    /**
     * Set the recipients of the message.
     *
     * @param  object|array|string $recipient
     *
     * @return $this
     */
    public function to($recipient): self
    {

        $hook_url = $this->config["slack_webhook_urls"][$recipient];

        if(!empty($hook_url)) {
            $this->anonymousNotifiable = \Notification::route('slack', $hook_url);
        } else {
            throw new \InvalidArgumentException("$recipient doesn't have a webhook URL associated with it in your laravel-slack config. Please add one.");
        }


        if ($recipient instanceof Collection) {
            $recipient = $recipient->all();
        }

        $recipients = is_array($recipient) ? $recipient : func_get_args();

        $this->recipients = array_map(function ($recipient) {
            if (is_object($recipient)) {
                return $recipient->slack_channel;
            }

            return $recipient;
        }, $recipients);

        return $this;
    }

    /**
     * Send a new message.
     *
     * @param string|SlackMessage $message
     *
     * @return void
     */
    public function send($message)
    {
        $slackMessages = $this->getSlackMessageArray($message);

        foreach ($slackMessages as $slackMessage) {
            $this->notify($slackMessage);
        }
    }

    protected function notify(SlackMessage $slackMessage)
    {
        $this->anonymousNotifiable->notify(new SimpleSlack($slackMessage));
    }

    /**
     * Send a new message.
     *
     * @param string|SlackMessage $message
     *
     * @return SlackMessage[]
     */
    protected function getSlackMessageArray($message): array
    {
        if ($message instanceof SlackMessage) {
            return [$message];
        }

        $slackMessageArray = [];
        $slackMessage = (new SlackMessage())->content($message);

        if ($this->from) {
            $slackMessage->from($this->from);
        }

        if ($this->image) {
            $slackMessage->image($this->image);
        }

        foreach ($this->recipients as $recipient) {
            $messageClone = clone $slackMessage;
            $slackMessageArray[] = $messageClone->to($recipient);
        }

        return $slackMessageArray;
    }
}
