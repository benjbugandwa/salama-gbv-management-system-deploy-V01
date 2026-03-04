<?php

namespace App\Livewire\Components;

use App\Models\AuditLog;
use App\Models\CaseNote;
use App\Models\Incident;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class IncidentCaseNotes extends Component
{
    use WithFileUploads;

    public string $incidentId;

    // public bool $showEditModal = false;
    public bool $editing = false;
    public ?int $editingId = null;
    public bool $showEditModal = false;

    public array $form = [
        'case_note' => '',
        'is_confidential' => false,
        'file_path' => null,
    ];

    public array $editForm = [
        'case_note' => '',
        'is_confidential' => false,
        'file_path' => null,
    ];

    public $file; // TemporaryUploadedFile|null

    public function mount(string $incidentId): void
    {
        $this->incidentId = $incidentId;
    }

    /* ------------------------- Guards & Helpers ------------------------- */

    private function userRole(): string
    {
        return Auth::user()->user_role ?? '';
    }

    private function canWrite(): bool
    {
        return in_array($this->userRole(), ['superadmin', 'admin', 'superviseur'], true);
    }

    private function incident(): Incident
    {
        return Incident::query()->findOrFail($this->incidentId);
    }

    private function isLocked(Incident $incident): bool
    {
        return in_array($incident->statut_incident, ['Cloturée', 'Archivé'], true);
    }

    private function sameProvinceAsUser(Incident $incident): bool
    {
        $u = Auth::user();
        if (($u->user_role ?? '') === 'superadmin') return true;
        return ($u->code_province ?? null) && ($u->code_province === $incident->code_province);
    }

    private function audit(string $action, string $modelType, ?string $modelUuid = null, array $meta = []): void
    {
        AuditLog::create([
            'id' => random_int(100000000, 999999999),
            'user_id' => Auth::id(),
            'user_action' => $action,
            'model_type' => $modelType,
            'model_id' => $modelUuid, // UUID ou NULL
            'ip_address' => request()->ip(),
            'action_meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function rules(): array
    {
        return [
            'form.case_note' => ['required', 'string', 'min:3'],
            'form.is_confidential' => ['boolean'],
            'file' => ['nullable', 'file', 'max:4096'], // 4MB
        ];
    }

    /* ------------------------------ UI ------------------------------ */

    public function openCreate(): void
    {
        $incident = $this->incident();

        if (!$this->canWrite()) {
            $this->dispatch('toast', message: "Vous n'avez pas l'autorisation d'ajouter une note.", type: 'error', duration: 6000);
            return;
        }
        if (!$this->sameProvinceAsUser($incident)) {
            $this->dispatch('toast', message: "Accès refusé (province).", type: 'error', duration: 6000);
            return;
        }
        if ($this->isLocked($incident)) {
            $this->dispatch('toast', message: "Incident clôturé/archivé : ajout de note impossible.", type: 'warning', duration: 6000);
            return;
        }

        $this->resetValidation();
        $this->editing = false;
        $this->editingId = null;
        $this->file = null;

        $this->form = [
            'case_note' => '',
            'is_confidential' => false,
            'file_path' => null,
        ];

        $this->showEditModal = true;
    }

    public function openEdit(int $id): void
    {
        $incident = $this->incident();

        if (!$this->canWrite()) {
            $this->dispatch('toast', message: "Vous n'avez pas l'autorisation de modifier une note.", type: 'error', duration: 6000);
            return;
        }
        if (!$this->sameProvinceAsUser($incident)) {
            $this->dispatch('toast', message: "Accès refusé (province).", type: 'error', duration: 6000);
            return;
        }
        if ($this->isLocked($incident)) {
            $this->dispatch('toast', message: "Incident clôturé/archivé : modification impossible.", type: 'warning', duration: 6000);
            return;
        }

        $note = CaseNote::query()
            ->where('id', $id)
            ->where('id_incident', $this->incidentId)
            ->firstOrFail();

        $this->resetValidation();
        $this->editing = true;
        $this->editingId = $note->id;
        $this->file = null;

        $this->editForm  = [
            'case_note' => $note->case_note ?? '',
            'is_confidential' => (bool)$note->is_confidential,
            'file_path' => $note->file_path,
        ];

        $this->showEditModal = true;
    }

    public function closeModalAndReset(): void
    {
        $this->showEditModal = false;
        $this->editing = false;
        $this->editingId = null;

        $this->form = [
            'case_note' => '',
            'is_confidential' => false,
            'file_path' => null,
        ];

        $this->editForm = [
            'case_note' => '',
            'is_confidential' => false,
            'file_path' => null,
        ];
    }

    public function save(): void
    {
        $incident = $this->incident();

        if (!$this->canWrite()) {
            $this->dispatch('toast', message: "Action non autorisée.", type: 'error', duration: 6000);
            return;
        }
        if (!$this->sameProvinceAsUser($incident)) {
            $this->dispatch('toast', message: "Accès refusé (province).", type: 'error', duration: 6000);
            return;
        }
        if ($this->isLocked($incident)) {
            $this->dispatch('toast', message: "Incident clôturé/archivé : opération impossible.", type: 'warning', duration: 6000);
            return;
        }

        $this->validate($this->rules());

        DB::transaction(function () use ($incident) {
            if ($this->editing && $this->editingId) {
                $note = CaseNote::query()
                    ->where('id', $this->editingId)
                    ->where('id_incident', $this->incidentId)
                    ->firstOrFail();

                $note->case_note = $this->editForm['case_note'];
                $note->is_confidential = (bool)$this->editForm['is_confidential'];

                if ($this->file) {
                    $path = $this->file->store('case-notes', 'public');
                    $note->file_path = $path;
                }

                $note->save();

                $this->audit('case_note_updated', 'case_note', null, [
                    'case_note_id' => $note->id,
                    'incident_id' => $incident->id,
                    'is_confidential' => (bool)$note->is_confidential,
                ]);

                $this->dispatch('toast', message: 'Note modifiée.', type: 'success', duration: 5000);
                $this->closeEditModal();

                return;
            }

            $note = new CaseNote();
            $note->id_incident = $this->incidentId;
            $note->case_note = $this->form['case_note'];
            $note->is_confidential = (bool)$this->form['is_confidential'];
            $note->created_by = Auth::id();

            if ($this->file) {
                $path = $this->file->store('case-notes', 'public');
                $note->file_path = $path;
            }

            $note->save();

            $this->audit('case_note_created', 'case_note', null, [
                'case_note_id' => $note->id,
                'incident_id' => $incident->id,
                'is_confidential' => (bool)$note->is_confidential,
            ]);
        });

        $this->dispatch('toast', message: $this->editing ? "Note mise à jour." : "Note ajoutée.", type: 'success', duration: 5000);
        $this->showEditModal = false;
        $this->editing = false;
        $this->editingId = null;
        $this->file = null;

        // Si tu veux rafraîchir un parent (Show) :
        $this->dispatch('case-notes-updated');
    }

    public function closeEditModal(): void
    {
        $this->showEditModal = false;
        $this->editingId = null;

        $this->editForm = [
            'case_note' => '',
            'is_confidential' => false,
            'file_path' => null,
        ];
    }

    public function update(): void
    {
        $incident = $this->incident();
        $this->validate([
            'editForm.case_note' => ['required', 'string', 'min:2'],
            'editForm.is_confidential' => ['boolean'],
        ]);

        $note = CaseNote::query()
            ->where('id', $this->editingId)
            ->where('id_incident', $this->incidentId)
            ->firstOrFail();

        $note->case_note = $this->editForm['case_note'];
        $note->is_confidential = (bool) $this->editForm['is_confidential'];
        if ($this->file) {
            $path = $this->file->store('case-notes', 'public');
            $note->file_path = $path;
        }
        $note->save();

        $this->audit('case_note_updated', 'case_note', null, [
            'case_note_id' => $note->id,
            'incident_id' => $incident->id,
            'is_confidential' => (bool)$note->is_confidential,
        ]);

        $this->dispatch('toast', message: 'Note modifiée.', type: 'success', duration: 5000);

        $this->closeEditModal();
    }

    public function getNotesProperty()
    {
        return CaseNote::query()
            ->where('id_incident', $this->incidentId)
            ->with('author:id,name')
            ->latest()
            ->get();
    }

    public function render()
    {
        $notes = CaseNote::query()
            ->where('id_incident', $this->incidentId)
            ->leftJoin('users', 'case_notes.created_by', '=', 'users.id')
            ->orderBy('case_notes.created_at', 'asc')
            ->get([
                'case_notes.*',
                DB::raw("COALESCE(users.name, '—') as author_name"),
            ]);

        $incident = $this->incident();

        return view('livewire.components.incident-case-notes', [
            'notes' => $notes,
            'incidentStatus' => $incident->statut_incident,
            'canWrite' => $this->canWrite() && !$this->isLocked($incident) && $this->sameProvinceAsUser($incident),
        ]);
    }
}
