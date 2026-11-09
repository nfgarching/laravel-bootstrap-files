<?php

namespace Nfgarching\Files\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class NewFiles
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $fileIds;

    public ?string $model_type;

    public ?int $model_id;

    public ?string $subject;

    public array $data;

    public string $route;

    /**
     * Create a new event instance.
     */
    public function __construct($fileIds, $model_type = null, $model_id = null, $subject = null, $data = [])
    {
        $this->fileIds = $fileIds;
        $this->model_type = $model_type ?? 'App\Models\User';
        $this->model_id = $model_id !== null && $model_id !== '' ? (int) $model_id : 0;
        $this->subject = $subject ?? '';
        $this->data = $data;

        // Log::channel('files')->info("NewFiles: Event erzeugt", [
        //     'model_type' => $this->model_type,
        //     'model_id' => $this->model_id,
        //     'subject' => $this->subject,
        //     'data' => $this->data,
        // ]);

        $this->route = route('files.index');
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
