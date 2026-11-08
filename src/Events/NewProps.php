<?php

namespace Nfgarching\Files\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class NewProps
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $file_ids;

    public $model_type;

    public $model_id;

    public $subject;

    public $switchTo;

    public ?int $documentId = null;

    public ?string $route = null;

    /**
     * Create a new event instance.
     */
    public function __construct($file_ids, $subject = '', $model_type = null, $model_id = null)
    {
        $this->file_ids = $file_ids;
        $this->subject = $subject;
        $this->model_type = $model_type;
        $this->model_id = $model_id;
        $this->switchTo = config('filesystems.new_files_listener', 'legacy');

        // Log::channel('files')->info(
        //     'NewFiles Event: Model Type: '.$model_type.' / Model ID: '.$model_id.' / File IDs: '.implode(', ', $file_ids)
        // );

        // dump($this);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-upload'),
        ];
    }
}
