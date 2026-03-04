<?php

namespace App\Livewire\Pages\Incidents;

use App\Models\Incident;
use App\Models\Survivant;
use App\Services\IncidentService;
use App\Exceptions\BusinessRuleException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination, WithFileUploads;

    /** Service */
    protected IncidentService $incidentService;

    // Filtres
    public string $q = '';
    public string $f_status = '';
    public string $f_severite = '';
    public string $f_province = '';
    public string $f_territoire = '';
    public string $f_zone = '';
    public ?string $date_from = null;
    public ?string $date_to = null;

    public int $perPage = 10;

    // Data dropdowns
    public array $provinces = [];
    public array $territoires = [];
    public array $zones = [];

    // Modal create/edit
    public bool $showModal = false;
    public bool $editing = false;
    public ?string $editingId = null;

    // Modal assign
    public bool $showAssignModal = false;
    public ?string $assignIncidentId = null;
    public ?string $assignTo = null; // uuid superviseur (users.id = uuid)
    public array $superviseursOptions = [];

    // Options
    public array $severityOptions = ['Faible', 'Elevée', 'Critique'];
    public array $confidentialityOptions = ['Standard', 'Protegé', 'Confidentielle'];

    // Confirmation modal
    public bool $showConfirmModal = false;
    public string $confirmTitle = '';
    public string $confirmMessage = '';
    public string $confirmAction = ''; // confirmValidate | confirmArchive
    public ?string $confirmIncidentId = null;

    // Upload
    public $photo; // TemporaryUploadedFile|null

    // Form
    public array $form = [
        'survivant_id' => null,
        'date_incident' => null,
        'severite' => '',
        'statut_incident' => 'En attente',
        'auteur_presume' => '',
        'code_province' => '',
        'code_territoire' => '',
        'code_zonesante' => '',
        'localite' => '',
        'source_info' => '',
        'description_faits' => '',
        'confidentiality_level' => 'Standard',
    ];

    /** Livewire DI */
    public function boot(IncidentService $incidentService): void
    {
        $this->incidentService = $incidentService;
    }

    public function mount(): void
    {
        $this->bootstrapScopeAndLists();
    }

    /* -------------------------------------------
     | Helpers rôles / scopes
     ------------------------------------------- */
    private function user()
    {
        return Auth::user();
    }

    private function isSuperAdmin(): bool
    {
        return $this->user()?->user_role === 'superadmin';
    }

    private function isAdmin(): bool
    {
        return $this->user()?->user_role === 'admin';
    }

    private function isSuperviseur(): bool
    {
        return $this->user()?->user_role === 'superviseur';
    }

    private function isMoniteur(): bool
    {
        return $this->user()?->user_role === 'moniteur';
    }

    private function sameProvinceAsUser(Incident $incident): bool
    {
        $u = $this->user();
        return $u && $u->code_province && $incident->code_province === $u->code_province;
    }

    private function isLocked(Incident $incident): bool
    {
        return in_array($incident->statut_incident, ['Cloturée', 'Archivé'], true);
    }

    private function canEditIncident(Incident $incident): bool
    {
        if ($this->isMoniteur()) return false;
        if ($this->isLocked($incident)) return false;
        if (!$this->isSuperAdmin() && !$this->sameProvinceAsUser($incident)) return false;
        return true;
    }

    /* -------------------------------------------
     | Lists (provinces/territoires/zones)
     ------------------------------------------- */
    private function bootstrapScopeAndLists(): void
    {
        $this->provinces = DB::table('provinces')
            ->select('code_province', 'nom_province')
            ->orderBy('nom_province')
            ->get()
            ->map(fn($p) => ['code' => $p->code_province, 'name' => $p->nom_province])
            ->toArray();

        if (!$this->isSuperAdmin()) {
            $this->f_province = $this->user()->code_province ?? '';
        }

        $this->loadTerritoires($this->f_province ?: null);
        $this->loadZones($this->f_territoire ?: null);
    }

    private function loadTerritoires(?string $codeProvince): void
    {
        if (!$codeProvince) {
            $this->territoires = [];
            return;
        }

        $this->territoires = DB::table('territoires')
            ->select('code_territoire', 'nom_territoire')
            ->where('code_province', $codeProvince)
            ->orderBy('nom_territoire')
            ->get()
            ->map(fn($t) => ['code' => $t->code_territoire, 'name' => $t->nom_territoire])
            ->toArray();
    }

    private function loadZones(?string $codeTerritoire): void
    {
        if (!$codeTerritoire) {
            $this->zones = [];
            return;
        }

        // ✅ table: zonesantes
        $this->zones = DB::table('zonesantes')
            ->select('code_zonesante', 'nom_zonesante')
            ->where('code_territoire', $codeTerritoire)
            ->orderBy('nom_zonesante')
            ->get()
            ->map(fn($z) => ['code' => $z->code_zonesante, 'name' => $z->nom_zonesante])
            ->toArray();
    }

    private function loadSuperviseursForProvince(string $codeProvince): void
    {
        // users.id supposé UUID (comme tu l’as indiqué pour assigned_to)
        $this->superviseursOptions = \App\Models\User::query()
            ->where('is_active', true)
            ->where('user_role', 'superviseur')
            ->where('code_province', $codeProvince)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn($u) => ['id' => (string)$u->id, 'name' => $u->name, 'email' => $u->email])
            ->toArray();
    }

    /* -------------------------------------------
     | Reactive hooks
     ------------------------------------------- */
    public function updatedFProvince(): void
    {
        $this->f_territoire = '';
        $this->f_zone = '';
        $this->loadTerritoires($this->f_province ?: null);
        $this->zones = [];
        $this->resetPage();
    }

    public function updatedFTerritoire(): void
    {
        $this->f_zone = '';
        $this->loadZones($this->f_territoire ?: null);
        $this->resetPage();
    }

    public function updatedFormCodeProvince(): void
    {
        $this->form['code_territoire'] = '';
        $this->form['code_zonesante'] = '';
        $this->loadTerritoires($this->form['code_province'] ?: null);
        $this->zones = [];
    }

    public function updatedFormCodeTerritoire(): void
    {
        $this->form['code_zonesante'] = '';
        $this->loadZones($this->form['code_territoire'] ?: null);
    }

    public function updatingQ(): void
    {
        $this->resetPage();
    }
    public function updatingFStatus(): void
    {
        $this->resetPage();
    }
    public function updatingFSeverite(): void
    {
        $this->resetPage();
    }
    public function updatingDateFrom(): void
    {
        $this->resetPage();
    }
    public function updatingDateTo(): void
    {
        $this->resetPage();
    }
    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    /* -------------------------------------------
     | Validation rules
     ------------------------------------------- */
    private function incidentRules(): array
    {
        return [
            'form.survivant_id' => ['nullable', 'uuid', Rule::exists('survivants', 'id')],

            'form.date_incident' => ['required', 'date', 'before_or_equal:today'],
            'form.severite' => ['required', Rule::in($this->severityOptions)],
            'form.statut_incident' => ['required', Rule::in(['En attente', 'Validé', 'Cloturée', 'Archivé'])],

            'form.auteur_presume' => ['nullable', 'string', 'max:255'],

            'form.code_province' => ['required', 'string', Rule::exists('provinces', 'code_province')],
            'form.code_territoire' => ['nullable', 'string', Rule::exists('territoires', 'code_territoire')],
            'form.code_zonesante' => ['nullable', 'string', Rule::exists('zonesantes', 'code_zonesante')],

            'form.localite' => ['nullable', 'string', 'max:255'],
            'form.source_info' => ['nullable', 'string', 'max:255'],
            'form.description_faits' => ['nullable', 'string'],

            'form.confidentiality_level' => ['required', Rule::in($this->confidentialityOptions)],

            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ];
    }

    /* -------------------------------------------
     | UI Actions (Create/Edit)
     ------------------------------------------- */
    public function openCreate(): void
    {
        $this->resetValidation();
        $this->editing = false;
        $this->editingId = null;
        $this->photo = null;

        $province = $this->isSuperAdmin()
            ? ($this->f_province ?: '')
            : ($this->user()->code_province ?? '');

        $this->form = [
            'survivant_id' => null,

            'date_incident' => now()->toDateString(),
            'severite' => 'Faible',
            'statut_incident' => 'En attente',
            'auteur_presume' => '',

            'code_province' => $province,
            'code_territoire' => '',
            'code_zonesante' => '',

            'localite' => '',
            'source_info' => '',
            'description_faits' => '',

            'confidentiality_level' => 'Standard',
        ];

        $this->loadTerritoires($this->form['code_province'] ?: null);
        $this->zones = [];

        $this->showModal = true;
    }

    public function openEdit(string $id): void
    {
        $this->resetValidation();
        $this->photo = null;

        $incident = Incident::findOrFail($id);

        if (!$this->canEditIncident($incident)) {
            $this->dispatch('toast', message: "Cet incident ne peut pas être modifié.", type: 'warning', duration: 6000);
            return;
        }

        $this->editing = true;
        $this->editingId = $id;

        $this->form = [
            'survivant_id' => $incident->survivant_id,

            'date_incident' => optional($incident->date_incident)->toDateString(),
            'severite' => $incident->severite ?? 'Faible',
            'statut_incident' => $incident->statut_incident ?? 'En attente',
            'auteur_presume' => $incident->auteur_presume ?? '',

            'code_province' => $incident->code_province ?? '',
            'code_territoire' => $incident->code_territoire ?? '',
            'code_zonesante' => $incident->code_zonesante ?? '',

            'localite' => $incident->localite ?? '',
            'source_info' => $incident->source_info ?? '',
            'description_faits' => $incident->description_faits ?? '',

            'confidentiality_level' => $incident->confidentiality_level ?? 'Standard',
        ];

        $this->loadTerritoires($this->form['code_province'] ?: null);
        $this->loadZones($this->form['code_territoire'] ?: null);

        $this->showModal = true;
    }

    /* -------------------------------------------
     | Save (Create/Update) -> SERVICE
     ------------------------------------------- */
    public function save(): void
    {
        $this->validate($this->incidentRules());

        // Province forcée pour non superadmin
        if (!$this->isSuperAdmin()) {
            $this->form['code_province'] = $this->user()->code_province;
        }

        try {
            if ($this->editing && $this->editingId) {
                $incident = Incident::findOrFail($this->editingId);

                if (!$this->canEditIncident($incident)) {
                    throw new BusinessRuleException("Modification refusée (incident verrouillé ou rôle).");
                }

                $this->incidentService->update(
                    incident: $incident,
                    payload: $this->form,
                    photo: $this->photo,
                    actor: $this->user(),
                    ipAddress: request()->ip()
                );

                $this->dispatch('toast', message: "Incident mis à jour.", type: 'success', duration: 5000);
                $this->showModal = false;
                return;
            }

            // Create
            $created = $this->incidentService->create(
                payload: $this->form,
                photo: $this->photo,
                actor: $this->user(),
                ipAddress: request()->ip()
            );

            $this->dispatch('toast', message: "Incident créé : {$created->code_incident}", type: 'success', duration: 5000);
            $this->showModal = false;
        } catch (BusinessRuleException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'warning', duration: 6500);
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', message: "Erreur interne. Veuillez réessayer.", type: 'error', duration: 6500);
        }
    }

    /* -------------------------------------------
     | Assign -> SERVICE
     ------------------------------------------- */
    public function openAssign(string $incidentId): void
    {
        $incident = Incident::findOrFail($incidentId);

        if ($this->isLocked($incident)) {
            $this->dispatch('toast', message: "Incident clôturé/archivé : assignation impossible.", type: 'warning', duration: 6000);
            return;
        }

        if ($this->isMoniteur()) {
            $this->dispatch('toast', message: "Un moniteur ne peut pas assigner.", type: 'warning', duration: 6000);
            return;
        }

        // Seuls admin/superadmin
        if (!($this->isSuperAdmin() || $this->isAdmin())) {
            $this->dispatch('toast', message: "Seul un admin peut assigner.", type: 'warning', duration: 6000);
            return;
        }

        if (!$this->isSuperAdmin() && !$this->sameProvinceAsUser($incident)) {
            $this->dispatch('toast', message: "Accès refusé.", type: 'error', duration: 6000);
            return;
        }

        $this->assignIncidentId = $incidentId;
        $this->assignTo = $incident->assigned_to ? (string)$incident->assigned_to : null;

        $this->loadSuperviseursForProvince($incident->code_province);
        $this->showAssignModal = true;
    }

    public function assign(): void
    {
        if (!$this->assignIncidentId) return;

        $incident = Incident::findOrFail($this->assignIncidentId);

        if ($this->isLocked($incident)) {
            $this->dispatch('toast', message: "Incident clôturé/archivé : assignation impossible.", type: 'warning', duration: 6000);
            return;
        }

        if (!($this->isSuperAdmin() || $this->isAdmin())) {
            $this->dispatch('toast', message: "Seul un admin peut assigner.", type: 'warning', duration: 6000);
            return;
        }

        if (!$this->isSuperAdmin() && !$this->sameProvinceAsUser($incident)) {
            $this->dispatch('toast', message: "Accès refusé.", type: 'error', duration: 6000);
            return;
        }

        $this->validate([
            'assignTo' => ['required'],
        ]);

        try {
            $this->incidentService->assignIncident(
                incident: $incident,
                superviseurId: $this->assignTo,
                actor: $this->user(),
                ipAddress: request()->ip()
            );

            $this->showAssignModal = false;
            $this->dispatch('toast', message: "Incident assigné avec succès.", type: 'success', duration: 5000);
        } catch (BusinessRuleException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'warning', duration: 6500);
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', message: "Erreur interne. Veuillez réessayer.", type: 'error', duration: 6500);
        }
    }

    /* -------------------------------------------
     | Confirm modals (Validate/Archive)
     ------------------------------------------- */
    public function askConfirmValidate(string $id): void
    {
        $this->confirmIncidentId = $id;
        $this->confirmTitle = "Valider l'incident ?";
        $this->confirmMessage = "Cette action va passer l'incident au statut « Validé ». Voulez-vous continuer ?";
        $this->confirmAction = 'confirmValidate';
        $this->showConfirmModal = true;
    }

    public function askConfirmArchive(string $id): void
    {
        $this->confirmIncidentId = $id;
        $this->confirmTitle = "Archiver l'incident ?";
        $this->confirmMessage = "L'incident sera archivé et ne sera plus visible dans la liste. Voulez-vous continuer ?";
        $this->confirmAction = 'confirmArchive';
        $this->showConfirmModal = true;
    }

    public function runConfirmAction(): void
    {
        if (!$this->confirmIncidentId) {
            $this->showConfirmModal = false;
            return;
        }

        $id = $this->confirmIncidentId;
        $action = $this->confirmAction;

        $this->showConfirmModal = false;
        $this->confirmIncidentId = null;

        if ($action === 'confirmValidate') {
            $this->validateIncident($id);
            return;
        }

        if ($action === 'confirmArchive') {
            $this->archive($id);
            return;
        }
    }

    /* -------------------------------------------
     | Validate -> SERVICE
     ------------------------------------------- */
    public function validateIncident(string $id): void
    {
        $incident = Incident::findOrFail($id);

        if ($this->isLocked($incident)) {
            $this->dispatch('toast', message: "Incident clôturé/archivé : validation impossible.", type: 'warning', duration: 6000);
            return;
        }

        if ($this->isMoniteur()) {
            $this->dispatch('toast', message: "Un moniteur ne peut pas valider.", type: 'warning', duration: 6000);
            return;
        }

        if (!$this->isSuperAdmin() && !$this->sameProvinceAsUser($incident)) {
            $this->dispatch('toast', message: "Accès refusé.", type: 'error', duration: 6000);
            return;
        }

        try {
            $this->incidentService->validateIncident(
                incident: $incident,
                actor: $this->user(),
                ipAddress: request()->ip()
            );

            //Audit log ici

            $this->dispatch('toast', message: "Incident validé.", type: 'success', duration: 5000);
        } catch (BusinessRuleException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'warning', duration: 6500);
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', message: "Erreur interne. Veuillez réessayer.", type: 'error', duration: 6500);
        }
    }

    /* -------------------------------------------
     | Archive -> SERVICE
     ------------------------------------------- */
    public function archive(string $id): void
    {
        $incident = Incident::findOrFail($id);

        if ($this->isMoniteur()) {
            $this->dispatch('toast', message: "Un moniteur ne peut pas archiver.", type: 'warning', duration: 6000);
            return;
        }

        if (!$this->isSuperAdmin() && !$this->sameProvinceAsUser($incident)) {
            $this->dispatch('toast', message: "Accès refusé.", type: 'error', duration: 6000);
            return;
        }

        try {
            $this->incidentService->archiveIncident(
                incident: $incident,
                actor: $this->user(),
                ipAddress: request()->ip()
            );

            $this->dispatch('toast', message: "Incident archivé.", type: 'success', duration: 5000);
        } catch (BusinessRuleException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'warning', duration: 6500);
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', message: "Erreur interne. Veuillez réessayer.", type: 'error', duration: 6500);
        }
    }

    /* -------------------------------------------
     | WhatsApp share (UI only)
     ------------------------------------------- */
    public function shareWhatsapp(string $id): void
    {
        $incident = Incident::query()
            ->leftJoin('provinces', 'incidents.code_province', '=', 'provinces.code_province')
            ->leftJoin('territoires', 'incidents.code_territoire', '=', 'territoires.code_territoire')
            ->leftJoin('zonesantes', 'incidents.code_zonesante', '=', 'zonesantes.code_zonesante')
            ->where('incidents.id', $id)
            ->select([
                'incidents.code_incident',
                'incidents.date_incident',
                'incidents.severite',
                'incidents.statut_incident',
                'incidents.localite',
                'provinces.nom_province',
                'territoires.nom_territoire',
                'zonesantes.nom_zonesante',
            ])->first();

        if (!$incident) return;

        $text =
            "Incident {$incident->code_incident}\n" .
            "Date: " . optional($incident->date_incident)->format('Y-m-d') . "\n" .
            "Province: " . ($incident->nom_province ?? '-') . "\n" .
            "Territoire: " . ($incident->nom_territoire ?? '-') . "\n" .
            "Zone: " . ($incident->nom_zonesante ?? '-') . "\n" .
            "Localité: " . ($incident->localite ?? '-') . "\n" .
            "Sévérité: " . ($incident->severite ?? '-') . "\n" .
            "Statut: " . ($incident->statut_incident ?? '-');

        $url = 'https://wa.me/?text=' . urlencode($text);
        $this->dispatch('open-url', url: $url);
    }

    /* -------------------------------------------
     | Render
     ------------------------------------------- */
    public function render()
    {
        // sleep(5);


        $user = $this->user();

        $query = Incident::query()
            ->where('incidents.statut_incident', '!=', 'Archivé')
            ->leftJoin('provinces', 'incidents.code_province', '=', 'provinces.code_province')
            ->leftJoin('zonesantes', 'incidents.code_zonesante', '=', 'zonesantes.code_zonesante')
            ->with(['violences:id,violence_name'])
            ->select([
                'incidents.*',
                DB::raw("COALESCE(provinces.nom_province, incidents.code_province, 'N/A') as province_name"),
                DB::raw("COALESCE(zonesantes.nom_zonesante, incidents.code_zonesante, 'N/A') as zone_name"),
            ]);

        // Scope province
        if (!$this->isSuperAdmin()) {
            $query->where('incidents.code_province', $user->code_province);
        } else {
            if ($this->f_province !== '') {
                $query->where('incidents.code_province', $this->f_province);
            }
        }

        // Filtres
        if ($this->q !== '') {
            $s = '%' . trim($this->q) . '%';
            $query->where('incidents.code_incident', 'ilike', $s);
        }

        if ($this->f_status !== '') $query->where('incidents.statut_incident', $this->f_status);
        if ($this->f_severite !== '') $query->where('incidents.severite', $this->f_severite);

        if ($this->f_territoire !== '') $query->where('incidents.code_territoire', $this->f_territoire);
        if ($this->f_zone !== '') $query->where('incidents.code_zonesante', $this->f_zone);

        if ($this->date_from) $query->whereDate('incidents.date_incident', '>=', $this->date_from);
        if ($this->date_to) $query->whereDate('incidents.date_incident', '<=', $this->date_to);

        $incidents = $query
            ->orderByDesc('incidents.date_incident')
            ->paginate($this->perPage);

        $survivants = Survivant::query()
            ->select('id', 'code_survivant', 'full_name')
            ->orderByDesc('code_survivant')
            ->limit(200)
            ->get();

        return view('livewire.pages.incidents.index', [
            'incidents' => $incidents,
            'survivants' => $survivants,
            'statuses' => ['En attente', 'Validé', 'Cloturée', 'Archivé'],
        ]);
    }
}
