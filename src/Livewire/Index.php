<?php

namespace Nfgarching\Files\Livewire;

use Livewire\Attributes\Lazy;
use Livewire\Component;
use Nfgarching\Core\Traits\ListingTrait;
use Nfgarching\Core\Traits\MainTrait;
use Nfgarching\Files\Models\File;

#[Lazy]
class Index extends Component
{
    use ListingTrait;
    use MainTrait;

    public ?string $model_type = null;

    public ?int $model_id = null;

    /** Zusätzliche Morph-Besitzer als [model_type, model_id]-Paare. */
    public array $owners = [];

    public function mount()
    {
        $this->build_model(File::class);
    }

    public function render()
    {
        $this->title = __('Files');
        $this->sortField = 'id';
        $this->sortAsc = true;
        $this->scope = $this->scope ? $this->scope : $this->table;
        $rows = File::search($this->search);

        $owners = $this->owners;
        if ($this->model_type && $this->model_id) {
            $owners[] = [$this->model_type, $this->model_id];
        }

        if (! empty($owners)) {
            $rows = $rows->where(function ($query) use ($owners) {
                foreach ($owners as [$type, $id]) {
                    $query->orWhere(fn ($q) => $q->where('model_type', $type)->where('model_id', $id));
                }
            });
        }

        if ($this->filter) {
            $this->title = 'Dokumente';
            $rows = $rows->where($this->filter, $this->where)
                ->orderBy($this->sortField, $this->sortAsc ? 'ASC' : 'DESC')
                ->orderBy('Datumzeit', 'DESC');
            // dd($this->filter, $rows);
        } else {
            $rows = $rows->orderBy($this->sortField, $this->sortAsc ? 'ASC' : 'DESC');
        }

        $paginated = $rows->paginate($this->pageLimit, pageName: 'filePage');

        return view('livewire::files.listing', [
            'rows' => $paginated,
            'fileIds' => $paginated->pluck('id')->all(),
        ]);
    }
}
