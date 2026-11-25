<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuthToken;
use App\Models\NisDirectory;
use App\Models\ReportingUnit;
use App\Models\User;
use App\Services\Messaging\ResendMailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class UserManagementController extends Controller
{
    public const NIS_RANKS = [
        'CGIS' => 'Comptroller-General of Immigration Service (CGIS)',
        'DCG' => 'Deputy Comptroller-General of Immigration (DCG)',
        'ACG' => 'Assistant Comptroller-General of Immigration (ACG)',
        'CIS' => 'Comptroller of Immigration Service (CIS)',
        'DCI' => 'Deputy Comptroller of Immigration (DCI)',
        'ACI' => 'Assistant Comptroller of Immigration (ACI)',
        'CSI' => 'Chief Superintendent of Immigration (CSI)',
        'SI' => 'Superintendent of Immigration (SI)',
        'DSI' => 'Deputy Superintendent of Immigration (DSI)',
        'ASI1' => 'Assistant Superintendent of Immigration I (ASI I)',
        'ASI2' => 'Assistant Superintendent of Immigration II (ASI II)',
        'II' => 'Inspector of Immigration (II)',
        'AII' => 'Assistant Inspector of Immigration (AII)',
        'CIA' => 'Chief Immigration Assistant (CIA)',
        'SIA' => 'Senior Immigration Assistant (SIA)',
        'IA1' => 'Immigration Assistant I (IA I)',
        'IA2' => 'Immigration Assistant II (IA II)',
        'IA3' => 'Immigration Assistant III (IA III)',
    ];

    public const STATES = [
        'AB' => 'Abia State',
        'AD' => 'Adamawa State',
        'AK' => 'Akwa Ibom State',
        'AN' => 'Anambra State',
        'BA' => 'Bauchi State',
        'BY' => 'Bayelsa State',
        'BE' => 'Benue State',
        'BO' => 'Borno State',
        'CR' => 'Cross River State',
        'DE' => 'Delta State',
        'EB' => 'Ebonyi State',
        'ED' => 'Edo State',
        'EK' => 'Ekiti State',
        'EN' => 'Enugu State',
        'FC' => 'FCT Abuja',
        'GO' => 'Gombe State',
        'IM' => 'Imo State',
        'JI' => 'Jigawa State',
        'KD' => 'Kaduna State',
        'KN' => 'Kano State',
        'KT' => 'Katsina State',
        'KE' => 'Kebbi State',
        'KO' => 'Kogi State',
        'KW' => 'Kwara State',
        'LA' => 'Lagos State',
        'NA' => 'Nasarawa State',
        'NI' => 'Niger State',
        'OG' => 'Ogun State',
        'ON' => 'Ondo State',
        'OS' => 'Osun State',
        'OY' => 'Oyo State',
        'PL' => 'Plateau State',
        'RI' => 'Rivers State',
        'SO' => 'Sokoto State',
        'TA' => 'Taraba State',
        'YO' => 'Yobe State',
        'ZA' => 'Zamfara State',
    ];

    public const ZONES = [
        'ZONE-A' => 'ZONE A - ZONE A HQ IKEJA',
        'ZONE-B' => 'ZONE B - ZONE B HQ KADUNA',
        'ZONE-C' => 'ZONE C - ZONE C HQ BAUCHI',
        'ZONE-D' => 'ZONE D - ZONE D HQ MINNA',
        'ZONE-E' => 'ZONE E - ZONE E HQ OWERRI',
        'ZONE-F' => 'ZONE F - ZONE F HQ IBADAN',
        'ZONE-G' => 'ZONE G - ZONE G HQ BENIN',
        'ZONE-H' => 'ZONE H - ZONE H HQ MAKURDI',
    ];

    public const STATE_TO_ZONE = [
        'LA' => 'ZONE-A', 'OG' => 'ZONE-A',
        'KN' => 'ZONE-B', 'KD' => 'ZONE-B', 'KT' => 'ZONE-B', 'ZA' => 'ZONE-B', 'SO' => 'ZONE-B', 'JI' => 'ZONE-B',
        'AD' => 'ZONE-C', 'BA' => 'ZONE-C', 'BO' => 'ZONE-C', 'GO' => 'ZONE-C', 'PL' => 'ZONE-C', 'YO' => 'ZONE-C',
        'FC' => 'ZONE-D', 'NI' => 'ZONE-D', 'KE' => 'ZONE-D', 'KW' => 'ZONE-D',
        'IM' => 'ZONE-E', 'RI' => 'ZONE-E', 'CR' => 'ZONE-E', 'EB' => 'ZONE-E', 'AK' => 'ZONE-E', 'AB' => 'ZONE-E',
        'OY' => 'ZONE-F', 'EK' => 'ZONE-F', 'ON' => 'ZONE-F', 'OS' => 'ZONE-F',
        'ED' => 'ZONE-G', 'AN' => 'ZONE-G', 'DE' => 'ZONE-G', 'EN' => 'ZONE-G', 'BY' => 'ZONE-G',
        'NA' => 'ZONE-H', 'BE' => 'ZONE-H', 'KO' => 'ZONE-H', 'TA' => 'ZONE-H',
    ];

    public const DIRECTORATES = [
        'hrm' => 'Human Resource Management (HRM)',
        'finance' => 'Finance & Accounts',
        'border' => 'Border Management',
        'migration' => 'Migration Directorate',
        'passport' => 'Passport & Other Travel Documents (POTD)',
        'visa' => 'Visa & Residency',
        'prs' => 'Planning, Research & Statistics (PRS)',
        'investigation' => 'Investigation & Compliance',
        'ict' => 'ICT & CyberSecurity',
        'works-logistics' => 'Works & Logistics',
    ];

    private const CGIS_UNITS = [
        'actu' => 'Anti-Corruption and Transparency Unit (ACTU)',
        'epms' => 'Electronic Passport Management System (EPMS)',
        'hostmanship' => 'Hostmanship Unit',
        'pro-media' => 'Public Relations / Media Unit',
        'protocol' => 'Protocol Unit',
        'provost' => 'Provost Unit',
        'servicom' => 'SERVICOM Unit',
    ];

    private function isActorSuperAdmin(?User $actor): bool
    {
        return $actor !== null
            && ($actor->user_category === 'super_admin' || $actor->role === 'super_admin');
    }

    private function formViewData(?User $actor): array
    {
        $categories = [
            'state_user' => 'Formation / Command Officer (State, Airport, Border, Marine, Mission)',
            'desk_admin' => 'Formation Desk Admin',
            'directorate_user' => 'Directorate Officer',
            'directorate_admin' => 'Directorate Admin',
            'zonal_commander' => 'Zonal Commander',
            'cgis_unit_user' => 'CGIS Unit Officer',
            'cgis_desk_admin' => 'CGIS Unit Desk Admin',
            'hq_admin' => 'HQ Admin (Approver)',
            'admin' => 'National Administrator (View-Only)',
        ];

        if ($this->isActorSuperAdmin($actor)) {
            $categories['super_admin'] = 'Super Administrator';
        }

        return [
            'categories' => $categories,
            'ranks' => self::NIS_RANKS,
            'states' => self::STATES,
            'formationGroups' => $this->directoryFormationGroups(),
            'formationZoneMap' => $this->formationZoneMap(),
            'zones' => self::ZONES,
            'directorates' => self::DIRECTORATES,
            'cgisUnits' => self::CGIS_UNITS,
            'locationTypes' => [
                'state' => 'State',
                'directorate' => 'Directorate',
                'zonal' => 'Zonal',
                'unit' => 'CGIS Unit',
                'headquarters' => 'Headquarters',
            ],
            'roles' => [
                'officer' => 'Officer',
                'state' => 'State Supervisor',
                'directorate' => 'Directorate User',
                'unit_officer' => 'CGIS Unit Officer',
                'unit_admin' => 'CGIS Unit Desk Admin',
                'zonal' => 'Zonal Commander',
                'admin' => 'Administrator',
            ],
        ];
    }

    /**
     * Load all official NIS Commands grouped by their authoritative Zone (ZONE-A..ZONE-H)
     * from config/nis_hierarchy.php, plus Foreign Missions from nis_directories.
     *
     * @return array<string, array<string, string>>
     */
    private function directoryFormationGroups(): array
    {
        $groups = [];
        $hierarchy = (array) config('nis_hierarchy.zones', []);

        foreach ($hierarchy as $zoneCode => $zoneInfo) {
            $groupLabel = str_replace('-', ' ', $zoneCode) . ' - ' . ($zoneInfo['name'] ?? $zoneCode);
            $options = [];
            foreach (($zoneInfo['commands'] ?? []) as $cmdCode => $cmdName) {
                $options[$cmdCode] = "{$cmdCode} - {$cmdName}";
            }
            if ($options !== []) {
                $groups[$groupLabel] = $options;
            }
        }

        if (Schema::hasTable('nis_directories')) {
            $missions = NisDirectory::query()
                ->where('category', 'foreign_mission')
                ->orderBy('name')
                ->get(['category', 'name', 'country', 'code']);

            if ($missions->isNotEmpty()) {
                $missionOpts = [];
                foreach ($missions as $row) {
                    $code = $this->directoryEntryCode($row);
                    $missionOpts[$code] = $row->country && ! str_contains(strtolower($row->name), strtolower($row->country))
                        ? "{$row->name} ({$row->country})"
                        : $row->name;
                }
                $groups['Foreign Missions'] = $missionOpts;
            }
        }

        return $groups;
    }

    /**
     * Map every official command code and 2-letter state code to its authoritative NIS Zone.
     *
     * @return array<string, string>
     */
    private function formationZoneMap(): array
    {
        $map = (array) config('nis_hierarchy.state_to_zone', self::STATE_TO_ZONE);
        $hierarchy = (array) config('nis_hierarchy.zones', []);

        foreach ($hierarchy as $zoneCode => $zoneInfo) {
            foreach (array_keys($zoneInfo['commands'] ?? []) as $cmdCode) {
                $map[strtoupper($cmdCode)] = $zoneCode;
            }
        }

        return $map;
    }

    private function directoryEntryCode(NisDirectory $row): string
    {
        if (! empty($row->code)) {
            return strtoupper(trim((string) $row->code));
        }

        if ($row->category === 'state_command' && ! empty($row->state)) {
            $stateCode = $this->stateNameToCode((string) $row->state);
            if ($stateCode !== null) {
                return $stateCode;
            }
        }

        $prefix = match ($row->category) {
            'airport' => 'AIR',
            'border' => 'BRD',
            'marine' => 'MRN',
            'passport' => 'PPT',
            'training' => 'TRN',
            'foreign_mission' => 'MSN',
            default => 'FRM',
        };

        return $prefix . '-' . strtoupper(Str::slug(Str::limit($row->name, 28, ''), '_'));
    }

    private function stateNameToCode(string $stateName): ?string
    {
        $needle = strtolower(trim(str_replace([' state', ' command'], '', strtolower($stateName))));
        if ($needle === 'fct' || str_contains($needle, 'abuja')) {
            return 'FC';
        }
        foreach (self::STATES as $code => $label) {
            $clean = strtolower(str_replace(' state', '', strtolower($label)));
            if ($needle === $clean || str_starts_with($clean, $needle) || str_starts_with($needle, $clean)) {
                return $code;
            }
        }

        return null;
    }

    public function index(Request $request)
    {
        $search = $request->string('search')->trim()->toString();

        $users = User::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('surname', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('service_number', 'like', "%{$search}%")
                        ->orWhere('rank', 'like', "%{$search}%");
                });
            })
            ->orderBy('name', 'asc')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', compact('users', 'search'));
    }

    public function create(Request $request)
    {
        return view('admin.users.create', $this->formViewData($request->user()));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'surname' => 'nullable|string|max:100|required_without:name',
            'first_name' => 'nullable|string|max:100|required_without:name',
            'middle_name' => 'nullable|string|max:100',
            'name' => 'nullable|string|max:255|required_without_all:surname,first_name',
            'rank' => ['nullable', 'string', 'in:'.implode(',', array_keys(self::NIS_RANKS))],
            'service_number' => ['required', 'string', 'regex:/^[0-9]{4,5}$/', 'unique:users'],
            'user_category' => 'required|in:state_user,desk_admin,directorate_user,directorate_admin,zonal_commander,cgis_unit_user,cgis_desk_admin,hq_admin,admin,super_admin',
            'role' => 'nullable|in:admin,super_admin,zonal,state,officer,directorate,unit_officer,unit_admin',
            'primary_location_type' => 'nullable|in:state,directorate,zonal,unit,headquarters',
            'primary_location_code' => 'nullable|string|max:50',
            'assigned_state_code' => 'nullable|string|max:50',
            'assigned_zonal_command_code' => 'nullable|string|max:50',
            'assigned_directorate_code' => 'nullable|string|max:50',
            'assigned_section_code' => 'nullable|string|max:80',
            'assigned_cgis_unit_code' => 'nullable|in:'.implode(',', array_keys(self::CGIS_UNITS)),
            'geo_state' => 'nullable|string|max:10',
            'geolocation_enabled' => 'sometimes|boolean',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'string', Password::min(6)->mixedCase()->numbers()->symbols(), 'confirmed'],
            'is_enabled' => 'sometimes|boolean',
        ], [
            'service_number.regex' => 'Service number must be 4 or 5 digits only (e.g. 1234 or 12345).',
        ]);

        // Privilege escalation guard: only Super Admin can create Super Admin accounts
        if ($validated['user_category'] === 'super_admin' && ! $this->isActorSuperAdmin($request->user())) {
            abort(403, 'Only a Super Administrator can create Super Admin accounts.');
        }

        $normalized = $this->normalizeAccessProfile(
            $validated['user_category'],
            $validated['primary_location_type'] ?? null,
            $validated['role'] ?? null
        );

        if ($normalized === null) {
            throw ValidationException::withMessages([
                'user_category' => ['Invalid access profile combination.'],
            ]);
        }

        $scopes = $this->resolveOperationalScopes($normalized['user_category'], $validated);
        $fullName = $this->composeFullName($validated);

        $user = User::create([
            'name' => $fullName,
            'surname' => isset($validated['surname']) ? trim($validated['surname']) : null,
            'first_name' => isset($validated['first_name']) ? trim($validated['first_name']) : null,
            'middle_name' => isset($validated['middle_name']) && trim($validated['middle_name']) !== '' ? trim($validated['middle_name']) : null,
            'rank' => $validated['rank'] ?? null,
            'service_number' => $validated['service_number'],
            'role' => $normalized['role'],
            'user_category' => $normalized['user_category'],
            'primary_location_type' => $normalized['primary_location_type'],
            'primary_location_code' => $scopes['primary_location_code'],
            'assigned_state_code' => $scopes['assigned_state_code'],
            'assigned_zonal_command_code' => $scopes['assigned_zonal_command_code'],
            'assigned_directorate_code' => $scopes['assigned_directorate_code'],
            'assigned_section_code' => $scopes['assigned_section_code'],
            'assigned_cgis_unit_code' => $scopes['assigned_cgis_unit_code'],
            'geo_state' => isset($validated['geo_state']) && $validated['geo_state'] !== '' ? strtoupper(trim($validated['geo_state'])) : null,
            'geolocation_enabled' => $request->has('geolocation_enabled') ? $request->boolean('geolocation_enabled') : true,
            'access_level' => $normalized['access_level'],
            'email' => strtolower(trim($validated['email'])),
            'password' => Hash::make($validated['password']),
            'must_change_password' => true,
            'is_enabled' => $request->boolean('is_enabled', true),
        ]);

        $this->sendVerificationEmail($user);

        \App\Services\AuditLogger::logCrud(
            user: $request->user(),
            action: 'user.created',
            entityType: 'user',
            entityId: $user->id,
            details: [
                'service_number' => $user->service_number,
                'rank' => $user->rank,
                'user_category' => $user->user_category,
                'primary_location_type' => $user->primary_location_type,
                'primary_location_code' => $user->primary_location_code,
                'assigned_directorate_code' => $user->assigned_directorate_code,
                'assigned_zonal_command_code' => $user->assigned_zonal_command_code,
                'geolocation_enabled' => $user->geolocation_enabled,
            ]
        );

        return redirect()->route('admin.users')
            ->with('status', sprintf('User %s (%s) created successfully. Verification email dispatched.', $user->name, $user->service_number));
    }

    public function edit(Request $request, User $user)
    {
        if ($user->user_category === 'super_admin' && ! $this->isActorSuperAdmin($request->user())) {
            abort(403, 'Only a Super Administrator can edit Super Admin accounts.');
        }

        return view('admin.users.edit', array_merge(
            ['user' => $user],
            $this->formViewData($request->user())
        ));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        if ($user->user_category === 'super_admin' && ! $this->isActorSuperAdmin($request->user())) {
            abort(403, 'Only a Super Administrator can modify Super Admin accounts.');
        }

        $validated = $request->validate([
            'surname' => 'nullable|string|max:100|required_without:name',
            'first_name' => 'nullable|string|max:100|required_without:name',
            'middle_name' => 'nullable|string|max:100',
            'name' => 'nullable|string|max:255|required_without_all:surname,first_name',
            'rank' => ['nullable', 'string', 'in:'.implode(',', array_keys(self::NIS_RANKS))],
            'service_number' => ['required', 'string', 'regex:/^[0-9]{4,5}$/', 'unique:users,service_number,'.$user->id],
            'user_category' => 'required|in:state_user,desk_admin,directorate_user,directorate_admin,zonal_commander,cgis_unit_user,cgis_desk_admin,hq_admin,admin,super_admin',
            'role' => 'nullable|in:admin,super_admin,zonal,state,officer,directorate,unit_officer,unit_admin',
            'primary_location_type' => 'nullable|in:state,directorate,zonal,unit,headquarters',
            'primary_location_code' => 'nullable|string|max:50',
            'assigned_state_code' => 'nullable|string|max:50',
            'assigned_zonal_command_code' => 'nullable|string|max:50',
            'assigned_directorate_code' => 'nullable|string|max:50',
            'assigned_section_code' => 'nullable|string|max:80',
            'assigned_cgis_unit_code' => 'nullable|in:'.implode(',', array_keys(self::CGIS_UNITS)),
            'geo_state' => 'nullable|string|max:10',
            'geolocation_enabled' => 'sometimes|boolean',
            'email' => 'required|string|email|max:255|unique:users,email,'.$user->id,
            'password' => ['nullable', 'string', Password::min(6)->mixedCase()->numbers()->symbols(), 'confirmed'],
            'is_enabled' => 'sometimes|boolean',
        ], [
            'service_number.regex' => 'Service number must be 4 or 5 digits only (e.g. 1234 or 12345).',
        ]);

        if ($validated['user_category'] === 'super_admin' && ! $this->isActorSuperAdmin($request->user())) {
            abort(403, 'Only a Super Administrator can assign the Super Admin role.');
        }

        $normalized = $this->normalizeAccessProfile(
            $validated['user_category'],
            $validated['primary_location_type'] ?? null,
            $validated['role'] ?? null
        );

        if ($normalized === null) {
            throw ValidationException::withMessages([
                'user_category' => ['Invalid access profile combination.'],
            ]);
        }

        $scopes = $this->resolveOperationalScopes($normalized['user_category'], $validated);
        $fullName = $this->composeFullName($validated);

        $user->update([
            'name' => $fullName,
            'surname' => isset($validated['surname']) ? trim($validated['surname']) : $user->surname,
            'first_name' => isset($validated['first_name']) ? trim($validated['first_name']) : $user->first_name,
            'middle_name' => array_key_exists('middle_name', $validated)
                ? (trim((string) $validated['middle_name']) !== '' ? trim((string) $validated['middle_name']) : null)
                : $user->middle_name,
            'rank' => array_key_exists('rank', $validated) ? ($validated['rank'] ?: null) : $user->rank,
            'service_number' => $validated['service_number'],
            'role' => $normalized['role'],
            'user_category' => $normalized['user_category'],
            'primary_location_type' => $normalized['primary_location_type'],
            'primary_location_code' => $scopes['primary_location_code'],
            'assigned_state_code' => $scopes['assigned_state_code'],
            'assigned_zonal_command_code' => $scopes['assigned_zonal_command_code'],
            'assigned_directorate_code' => $scopes['assigned_directorate_code'],
            'assigned_section_code' => $scopes['assigned_section_code'],
            'assigned_cgis_unit_code' => $scopes['assigned_cgis_unit_code'],
            'geo_state' => isset($validated['geo_state']) && $validated['geo_state'] !== '' ? strtoupper(trim($validated['geo_state'])) : null,
            'geolocation_enabled' => $request->has('geolocation_enabled') ? $request->boolean('geolocation_enabled') : $user->isGeolocationEnabled(),
            'access_level' => $normalized['access_level'],
            'email' => strtolower(trim($validated['email'])),
            'is_enabled' => $request->boolean('is_enabled', true),
            'password' => ! empty($validated['password']) ? Hash::make($validated['password']) : $user->password,
            'must_change_password' => ! empty($validated['password']) ? true : $user->must_change_password,
        ]);

        $changedFields = array_keys($user->getChanges());
        $changedFields = array_values(array_diff($changedFields, ['password', 'remember_token', 'updated_at']));

        \App\Services\AuditLogger::logCrud(
            user: $request->user(),
            action: 'user.updated',
            entityType: 'user',
            entityId: $user->id,
            details: [
                'changed_fields' => $changedFields,
                'password_reset_by_admin' => ! empty($validated['password']),
            ]
        );

        return redirect()->route('admin.users.edit', $user)
            ->with('status', 'User updated successfully.');
    }

    public function toggleStatus(User $user): RedirectResponse
    {
        if ($user->user_category === 'super_admin' && ! $this->isActorSuperAdmin(request()->user())) {
            abort(403, 'Only a Super Administrator can enable or disable Super Admin accounts.');
        }

        $user->update(['is_enabled' => ! $user->is_enabled]);

        \App\Services\AuditLogger::logCrud(
            user: request()->user(),
            action: 'user.status_toggled',
            entityType: 'user',
            entityId: $user->id,
            details: ['is_enabled' => (bool) $user->is_enabled]
        );

        return redirect()->route('admin.users')
            ->with('status', sprintf('User %s has been %s.', $user->name, $user->is_enabled ? 'enabled' : 'disabled'));
    }

    private function composeFullName(array $validated): string
    {
        $parts = array_filter([
            isset($validated['surname']) ? trim((string) $validated['surname']) : '',
            isset($validated['first_name']) ? trim((string) $validated['first_name']) : '',
            isset($validated['middle_name']) ? trim((string) $validated['middle_name']) : '',
        ], static fn (string $p): bool => $p !== '');

        if ($parts !== []) {
            return implode(' ', $parts);
        }

        return trim((string) ($validated['name'] ?? ''));
    }

    /**
     * Derive operational scope columns automatically from category and selected location.
     *
     * @return array{primary_location_code: ?string, assigned_state_code: ?string, assigned_zonal_command_code: ?string, assigned_directorate_code: ?string, assigned_cgis_unit_code: ?string}
     */
    private function resolveOperationalScopes(string $category, array $validated): array
    {
        $primaryCode = isset($validated['primary_location_code']) && trim((string) $validated['primary_location_code']) !== ''
            ? trim((string) $validated['primary_location_code'])
            : null;
        $stateCode = isset($validated['assigned_state_code']) && trim((string) $validated['assigned_state_code']) !== ''
            ? strtoupper(trim((string) $validated['assigned_state_code']))
            : null;
        $zoneCode = isset($validated['assigned_zonal_command_code']) && trim((string) $validated['assigned_zonal_command_code']) !== ''
            ? strtoupper(trim((string) $validated['assigned_zonal_command_code']))
            : null;
        $dirCode = isset($validated['assigned_directorate_code']) && trim((string) $validated['assigned_directorate_code']) !== ''
            ? strtolower(trim((string) $validated['assigned_directorate_code']))
            : null;
        $sectionCode = isset($validated['assigned_section_code']) && trim((string) $validated['assigned_section_code']) !== ''
            ? strtoupper(trim((string) $validated['assigned_section_code']))
            : null;
        $cgisCode = isset($validated['assigned_cgis_unit_code']) && trim((string) $validated['assigned_cgis_unit_code']) !== ''
            ? strtolower(trim((string) $validated['assigned_cgis_unit_code']))
            : null;

        if (in_array($category, ['state_user', 'desk_admin'], true)) {
            $resolvedState = $stateCode ?? ($primaryCode ? strtoupper($primaryCode) : null);
            $resolvedZone = $zoneCode;
            if ($resolvedZone === null && $resolvedState !== null) {
                $unit = ReportingUnit::query()->where('code', $resolvedState)->with('parent')->first();
                $resolvedZone = $unit?->parent?->code
                    ?? ($this->formationZoneMap()[$resolvedState] ?? null);
            }

            return [
                'primary_location_code' => $resolvedState,
                'assigned_state_code' => $resolvedState,
                'assigned_zonal_command_code' => $resolvedZone,
                'assigned_directorate_code' => null,
                'assigned_section_code' => null,
                'assigned_cgis_unit_code' => null,
            ];
        }

        if ($category === 'zonal_commander') {
            $resolvedZone = $zoneCode ?? ($primaryCode ? strtoupper($primaryCode) : null);

            return [
                'primary_location_code' => $resolvedZone,
                'assigned_state_code' => null,
                'assigned_zonal_command_code' => $resolvedZone,
                'assigned_directorate_code' => null,
                'assigned_section_code' => null,
                'assigned_cgis_unit_code' => null,
            ];
        }

        if (in_array($category, ['directorate_user', 'directorate_admin'], true)) {
            $resolvedDir = $dirCode ?? ($primaryCode ? strtolower($primaryCode) : null);

            return [
                'primary_location_code' => $resolvedDir ? strtoupper($resolvedDir) : null,
                'assigned_state_code' => null,
                'assigned_zonal_command_code' => null,
                'assigned_directorate_code' => $resolvedDir,
                'assigned_section_code' => $category === 'directorate_user' ? $sectionCode : null,
                'assigned_cgis_unit_code' => null,
            ];
        }

        if (in_array($category, ['cgis_unit_user', 'cgis_desk_admin'], true)) {
            $resolvedCgis = $cgisCode ?? ($primaryCode ? strtolower($primaryCode) : null);

            return [
                'primary_location_code' => $resolvedCgis,
                'assigned_state_code' => null,
                'assigned_zonal_command_code' => null,
                'assigned_directorate_code' => null,
                'assigned_section_code' => null,
                'assigned_cgis_unit_code' => $resolvedCgis,
            ];
        }

        return [
            'primary_location_code' => 'HQ',
            'assigned_state_code' => null,
            'assigned_zonal_command_code' => null,
            'assigned_directorate_code' => null,
            'assigned_section_code' => null,
            'assigned_cgis_unit_code' => null,
        ];
    }

    private function normalizeAccessProfile(string $category, ?string $locationType, ?string $role): ?array
    {
        $map = [
            'state_user' => [
                'role' => ['officer'],
                'location' => 'state',
                'level' => 0,
                'canonical_role' => 'officer',
            ],
            'desk_admin' => [
                'role' => ['state', 'admin'],
                'location' => 'state',
                'level' => 1,
                'canonical_role' => 'state',
            ],
            'directorate_user' => [
                'role' => ['directorate'],
                'location' => 'directorate',
                'level' => 0,
                'canonical_role' => 'directorate',
            ],
            'directorate_admin' => [
                'role' => ['admin', 'directorate'],
                'location' => 'directorate',
                'level' => 3,
                'canonical_role' => 'admin',
            ],
            'zonal_commander' => [
                'role' => ['zonal', 'admin'],
                'location' => 'zonal',
                'level' => 4,
                'canonical_role' => 'zonal',
            ],
            'cgis_unit_user' => [
                'role' => ['unit_officer'],
                'location' => 'unit',
                'level' => 0,
                'canonical_role' => 'unit_officer',
            ],
            'cgis_desk_admin' => [
                'role' => ['unit_admin'],
                'location' => 'unit',
                'level' => 2,
                'canonical_role' => 'unit_admin',
            ],
            'hq_admin' => [
                'role' => ['admin'],
                'location' => 'headquarters',
                'level' => 5,
                'canonical_role' => 'admin',
            ],
            'admin' => [
                'role' => ['admin'],
                'location' => 'headquarters',
                'level' => 5,
                'canonical_role' => 'admin',
            ],
            'super_admin' => [
                'role' => ['admin', 'super_admin'],
                'location' => 'headquarters',
                'level' => 6,
                'canonical_role' => 'admin',
            ],
        ];

        if (! array_key_exists($category, $map)) {
            return null;
        }

        $profile = $map[$category];

        if ($role !== null && $role !== '' && ! in_array($role, $profile['role'], true)) {
            return null;
        }

        if ($locationType !== null && $locationType !== '' && $locationType !== $profile['location']) {
            return null;
        }

        return [
            'role' => $profile['canonical_role'],
            'user_category' => $category,
            'primary_location_type' => $profile['location'],
            'access_level' => $profile['level'],
        ];
    }

    private function sendVerificationEmail(User $user): void
    {
        $ttlSeconds = (int) config('auth.token_ttl', 3600);
        $rawToken = Str::random(64);
        $tokenHash = hash('sha256', $rawToken);

        AuthToken::create([
            'user_id' => $user->id,
            'type' => 'email_verify',
            'token_hash' => $tokenHash,
            'expires_at' => now()->addSeconds($ttlSeconds),
            'used_at' => null,
        ]);

        $frontendUrl = (string) config('app.url', 'http://localhost');
        $magicLink = rtrim($frontendUrl, '/').'/verify-email/'.$rawToken;

        try {
            $mailer = new ResendMailService;
            $mailer->sendFromTemplate(
                'verify_email_magic_link',
                [
                    'name' => $user->name,
                    'magic_link' => $magicLink,
                    'expires_in_minutes' => (int) ceil($ttlSeconds / 60),
                ],
                $user->email,
                $user->name
            );
        } catch (\Throwable $e) {
            Log::error('Admin user creation email delivery failed', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
