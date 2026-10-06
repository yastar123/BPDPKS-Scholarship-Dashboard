<?php

namespace App\Http\Controllers;

use App\Models\AcademicSemesterRecord;
use App\Models\AlumniPlacement;
use App\Models\BudgetAllocation;
use App\Models\Campus;
use App\Models\Internship;
use App\Models\ProgramQuota;
use App\Models\ScholarshipApplicant;
use App\Models\SemesterFunding;
use App\Models\StudyProgram;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScholarshipController extends Controller
{
    private const SELECTION_PATHS = [
        'pekebun', 'keluarga_pekebun', 'karyawan_sawit', 'keluarga_karyawan',
        'pengurus_asosiasi', 'asn', 'penyuluh',
    ];

    private const SELECTION_STATUSES = [
        'mendaftar', 'lolos_administrasi', 'lolos_tes', 'diterima',
        'mengundurkan_diri',
    ];

    public function health(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $cohort = (int) ($request->query('cohort') ?: ScholarshipApplicant::max('cohort') ?: now()->year);
        $all = ScholarshipApplicant::with(['academicRecords', 'fundingRecords', 'internships'])
            ->where('cohort', $cohort)->get();
        $recipients = $all->where('selection_status', 'diterima');
        $graduates = $recipients->where('study_status', 'lulus')->count();
        $dropouts = $recipients->where('study_status', 'putus')->count();

        $budgetAllocations = BudgetAllocation::where('cohort', $cohort)
            ->pluck('amount', 'study_program_id');
        $capacities = ProgramQuota::with('program.campus')
            ->where('cohort', $cohort)
            ->get()
            ->map(function (ProgramQuota $allocation) use ($recipients, $budgetAllocations): array {
                $program = $allocation->program;
                $occupied = $recipients->where('study_program_id', $program->id)->count();

                return [
                    'campus' => $program->campus->name,
                    'program' => $program->name,
                    'degree' => $program->degree,
                    'cohort' => (int) $allocation->cohort,
                    'quota' => (int) $allocation->quota,
                    'occupied' => $occupied,
                    'budgetCeiling' => (float) ($budgetAllocations[$program->id] ?? 0),
                ];
            })
            ->sortBy(['campus', 'program'])
            ->values();

        $budgetCeiling = (float) BudgetAllocation::where('cohort', $cohort)->sum('amount');
        $budgetRealized = (float) $all->sum(
            fn (ScholarshipApplicant $applicant) => $applicant->fundingRecords->sum('amount')
        );
        $trend = ScholarshipApplicant::query()
            ->selectRaw('cohort, COUNT(*) as applicants')
            ->selectRaw("SUM(CASE WHEN selection_status = 'diterima' THEN 1 ELSE 0 END) as recipients")
            ->selectRaw("SUM(CASE WHEN selection_status = 'diterima' AND study_status = 'lulus' THEN 1 ELSE 0 END) as graduates")
            ->selectRaw("SUM(CASE WHEN selection_status = 'diterima' AND study_status = 'putus' THEN 1 ELSE 0 END) as dropouts")
            ->groupBy('cohort')
            ->orderBy('cohort')
            ->get()
            ->map(fn ($row) => [
                'cohort' => (int) $row->cohort,
                'applicants' => (int) $row->applicants,
                'recipients' => (int) $row->recipients,
                'graduates' => (int) $row->graduates,
                'dropouts' => (int) $row->dropouts,
            ])
            ->values();

        $provinceCounts = $recipients->countBy('province')->map(
            fn ($count, $label) => ['label' => $label, 'count' => $count]
        )->values();
        $pathCounts = $recipients->countBy('selection_path')->map(
            fn ($count, $label) => ['label' => $label, 'count' => $count]
        )->values();
        $quotaTotal = (int) $capacities->sum('quota');
        $occupiedTotal = $recipients->count();
        $academicRisk = $recipients->filter(
            fn (ScholarshipApplicant $applicant) =>
                in_array($applicant->study_status, ['aktif', 'cuti'], true)
                && $applicant->latest_gpa !== null
                && $applicant->latest_gpa < 2.75
        )->count();

        return response()->json([
            'cohort' => $cohort,
            'totalApplicants' => $all->count(),
            'recipients' => $occupiedTotal,
            'totalQuota' => $quotaTotal,
            'occupiedQuota' => $occupiedTotal,
            'quotaFillRate' => $this->percentage($occupiedTotal, $quotaTotal),
            'graduates' => $graduates,
            'dropouts' => $dropouts,
            'graduationRate' => $this->percentage($graduates, $occupiedTotal),
            'dropoutRate' => $this->percentage($dropouts, $occupiedTotal),
            'budgetCeiling' => $budgetCeiling,
            'budgetRealized' => $budgetRealized,
            'budgetRealizationRate' => $this->percentage($budgetRealized, $budgetCeiling),
            'byProvince' => $provinceCounts,
            'byPath' => $pathCounts,
            'campusCapacity' => $capacities,
            'cohortTrend' => $trend,
            'decisionFlags' => [
                'ageOverLimit' => $all->filter(fn (ScholarshipApplicant $applicant) =>
                    $this->ageAtRegistration($applicant) > 23
                )->count(),
                'quotaAtRisk' => $capacities->filter(fn (array $capacity) =>
                    $capacity['quota'] > 0 && $capacity['occupied'] >= $capacity['quota'] * 0.9
                )->count(),
                'academicRisk' => $academicRisk,
                'certificatePending' => Internship::where('certificate_issued', false)
                    ->whereHas('applicant', fn (Builder $query) =>
                        $query->where('cohort', $cohort)->where('selection_status', 'diterima')
                    )->count(),
            ],
        ]);
    }

    public function saveCapacity(Request $request): JsonResponse
    {
        $data = $request->validate([
            'campus' => ['required', 'string', 'min:2', 'max:180'],
            'campusProvince' => ['sometimes', 'nullable', 'string', 'max:100'],
            'program' => ['required', 'string', 'min:2', 'max:180'],
            'degree' => ['required', 'in:D1,D2,D3,D4/S1'],
            'cohort' => ['required', 'integer', 'min:2000', 'max:2100'],
            'quota' => ['required', 'integer', 'min:0'],
            'budgetCeiling' => ['required', 'numeric', 'min:0'],
        ]);

        $capacity = DB::transaction(function () use ($data): array {
            $campus = Campus::firstOrCreate(
                ['name' => trim($data['campus'])],
                ['province' => $data['campusProvince'] ?? null]
            );
            if (
                array_key_exists('campusProvince', $data)
                && $data['campusProvince'] !== null
                && $campus->province !== $data['campusProvince']
            ) {
                $campus->province = $data['campusProvince'];
                $campus->save();
            }
            $program = StudyProgram::firstOrCreate([
                'campus_id' => $campus->id,
                'name' => trim($data['program']),
                'degree' => $data['degree'],
            ]);
            StudyProgram::whereKey($program->id)->lockForUpdate()->firstOrFail();
            ProgramQuota::where('study_program_id', $program->id)
                ->where('cohort', $data['cohort'])
                ->lockForUpdate()->first();
            $occupied = ScholarshipApplicant::where('study_program_id', $program->id)
                ->where('cohort', $data['cohort'])
                ->where('selection_status', 'diterima')
                ->count();

            if ($data['quota'] < $occupied) {
                throw ValidationException::withMessages([
                    'quota' => "Kuota tidak dapat di bawah {$occupied} penerima yang sudah diterima.",
                ]);
            }

            $allocation = ProgramQuota::updateOrCreate(
                ['study_program_id' => $program->id, 'cohort' => $data['cohort']],
                ['quota' => $data['quota']]
            );
            BudgetAllocation::updateOrCreate(
                ['study_program_id' => $program->id, 'cohort' => $data['cohort']],
                ['amount' => $data['budgetCeiling']]
            );

            return [
                'campus' => $campus->name,
                'program' => $program->name,
                'degree' => $program->degree,
                'cohort' => (int) $allocation->cohort,
                'quota' => (int) $allocation->quota,
                'occupied' => $occupied,
                'budgetCeiling' => (float) $data['budgetCeiling'],
            ];
        });

        return response()->json($capacity);
    }

    public function applicants(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:150'],
            'cohort' => ['sometimes', 'nullable', 'integer', 'min:2000', 'max:2100'],
            'status' => ['sometimes', 'nullable', 'in:'.implode(',', self::SELECTION_STATUSES)],
            'province' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $query = ScholarshipApplicant::query()
            ->with(['campus', 'program', 'academicRecords', 'fundingRecords', 'internships', 'placement'])
            ->when($filters['cohort'] ?? null, fn (Builder $query, $cohort) => $query->where('cohort', $cohort))
            ->when($filters['status'] ?? null, fn (Builder $query, $status) => $query->where('selection_status', $status))
            ->when($filters['province'] ?? null, fn (Builder $query, $province) => $query->where('province', $province))
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(fn (Builder $nested) => $nested
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('registration_number', 'like', "%{$search}%")
                    ->orWhere('school_name', 'like', "%{$search}%")
                    ->orWhereHas('campus', fn (Builder $campus) => $campus->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('program', fn (Builder $program) => $program->where('name', 'like', "%{$search}%"))
                );
            })
            ->orderByDesc('cohort')
            ->orderBy('name')
            ->get();

        return response()->json([
            'items' => $query->map(fn (ScholarshipApplicant $applicant) => $this->serializeApplicant($applicant))->values(),
            'total' => $query->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->applicantRules());
        $registeredAt = now();
        $age = $this->ageOn($data['birthDate'], $registeredAt);
        $this->assertAgeEligibleForSelection($age, $data['selectionStatus']);

        $applicant = DB::transaction(function () use ($data, $registeredAt): ScholarshipApplicant {
            $campus = Campus::firstOrCreate(
                ['name' => trim($data['campus'])],
                ['province' => trim($data['province'])]
            );
            $program = StudyProgram::firstOrCreate([
                'campus_id' => $campus->id,
                'name' => trim($data['program']),
                'degree' => $data['degree'],
            ]);

            if ($data['selectionStatus'] === 'diterima') {
                $this->assertQuotaAvailable($program->id, $data['cohort']);
            }

            return ScholarshipApplicant::create([
                'registration_number' => trim($data['registrationNumber']),
                'name' => trim($data['name']),
                'gender' => $data['gender'],
                'birth_date' => $data['birthDate'],
                'school_name' => trim($data['schoolName']),
                'school_type' => $data['schoolType'],
                'province' => trim($data['province']),
                'regency' => trim($data['regency']),
                'selection_path' => $data['selectionPath'],
                'selection_status' => $data['selectionStatus'],
                'campus_id' => $campus->id,
                'study_program_id' => $program->id,
                'cohort' => $data['cohort'],
                'study_status' => 'aktif',
                'registered_at' => $registeredAt,
            ]);
        });

        return response()->json(
            $this->serializeApplicant($applicant->load(['campus', 'program', 'academicRecords', 'fundingRecords', 'internships', 'placement'])),
            201
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'selectionStatus' => ['sometimes', 'in:'.implode(',', self::SELECTION_STATUSES)],
            'gpa' => ['sometimes', 'nullable', 'numeric', 'between:0,4'],
            'activeSemester' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'studyStatus' => ['sometimes', 'in:aktif,cuti,putus,lulus'],
            'graduationDate' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'studyDurationMonths' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'internshipLocation' => ['sometimes', 'nullable', 'string', 'max:180'],
            'internshipPeriod' => ['sometimes', 'nullable', 'string', 'max:100'],
            'certificateIssued' => ['sometimes', 'boolean'],
            'placementStatus' => ['sometimes', 'in:belum_lulus,belum_ditempatkan,bekerja,wirausaha'],
            'academicRecords' => ['sometimes', 'array'],
            'academicRecords.*.semester' => ['required_with:academicRecords', 'integer', 'min:1'],
            'academicRecords.*.ip' => ['required_with:academicRecords', 'numeric', 'between:0,4'],
            'fundingBySemester' => ['sometimes', 'array'],
            'fundingBySemester.*.semester' => ['required_with:fundingBySemester', 'integer', 'min:1'],
            'fundingBySemester.*.tuitionPaid' => ['required_with:fundingBySemester', 'numeric', 'min:0'],
            'fundingBySemester.*.stipendAndBooks' => ['required_with:fundingBySemester', 'numeric', 'min:0'],
            'fundingBySemester.*.stipend' => ['required_with:fundingBySemester', 'numeric', 'min:0'],
            'fundingBySemester.*.books' => ['required_with:fundingBySemester', 'numeric', 'min:0'],
            'fundingBySemester.*.transport' => ['required_with:fundingBySemester', 'numeric', 'min:0'],
            'fundingBySemester.*.graduationCost' => ['required_with:fundingBySemester', 'numeric', 'min:0'],
        ]);

        $applicant = ScholarshipApplicant::with(['campus', 'program', 'academicRecords', 'fundingRecords', 'internships', 'placement'])->findOrFail($id);
        $studyFields = [
            'gpa', 'activeSemester', 'studyStatus', 'graduationDate', 'studyDurationMonths',
            'academicRecords', 'fundingBySemester', 'internshipLocation', 'internshipPeriod',
            'certificateIssued', 'placementStatus',
        ];
        $nextSelectionStatus = $data['selectionStatus'] ?? $applicant->selection_status;
        if (
            $nextSelectionStatus !== 'diterima'
            && array_intersect(array_keys($data), $studyFields) !== []
        ) {
            throw ValidationException::withMessages([
                'selectionStatus' => 'Data akademik, magang, dan pendanaan hanya dapat diubah untuk penerima beasiswa.',
            ]);
        }

        DB::transaction(function () use ($applicant, $data): void {
            if (array_key_exists('selectionStatus', $data)) {
                $this->assertSelectionTransition($applicant, $data['selectionStatus']);
                if ($data['selectionStatus'] === 'diterima' && $applicant->selection_status !== 'diterima') {
                    $this->assertAgeEligibleForSelection(
                        $this->ageAtRegistration($applicant),
                        $data['selectionStatus']
                    );
                    $this->assertQuotaAvailable($applicant->study_program_id, $applicant->cohort, $applicant->id);
                }
                $applicant->selection_status = $data['selectionStatus'];
            }

            if (array_key_exists('gpa', $data)) {
                $applicant->latest_gpa = $data['gpa'];
            }
            if (array_key_exists('activeSemester', $data)) {
                $applicant->active_semester = $data['activeSemester'];
            }
            if (array_key_exists('studyStatus', $data)) {
                $applicant->study_status = $data['studyStatus'];
                if ($data['studyStatus'] === 'lulus' && ! $applicant->graduation_date) {
                    $applicant->graduation_date = now()->toDateString();
                }
            }
            if (array_key_exists('graduationDate', $data)) {
                $applicant->graduation_date = $data['graduationDate'];
            }
            if (array_key_exists('studyDurationMonths', $data)) {
                $applicant->study_duration_months = $data['studyDurationMonths'];
            }
            if (
                ($data['graduationDate'] ?? null) !== null
                && ($data['studyStatus'] ?? $applicant->study_status) !== 'lulus'
            ) {
                throw ValidationException::withMessages([
                    'graduationDate' => 'Tanggal lulus hanya dapat diisi untuk mahasiswa berstatus lulus.',
                ]);
            }
            $applicant->save();

            if (array_key_exists('academicRecords', $data)) {
                $this->replaceAcademicRecords($applicant, $data['academicRecords']);
            }
            if (array_key_exists('fundingBySemester', $data)) {
                $this->replaceFundingRecords($applicant, $data['fundingBySemester']);
            }
            if (
                array_key_exists('internshipLocation', $data)
                || array_key_exists('internshipPeriod', $data)
                || array_key_exists('certificateIssued', $data)
            ) {
                $latestInternship = $applicant->internships()->latest('id')->first();
                $location = $data['internshipLocation'] ?? $latestInternship?->location;
                $period = $data['internshipPeriod'] ?? $latestInternship?->period;
                $certificate = $data['certificateIssued'] ?? $latestInternship?->certificate_issued ?? false;
                if ($location && $period) {
                    $applicant->internships()->updateOrCreate(
                        ['id' => $latestInternship?->id],
                        ['location' => $location, 'period' => $period, 'certificate_issued' => $certificate]
                    );
                } elseif ($certificate) {
                    throw ValidationException::withMessages([
                        'certificateIssued' => 'Lokasi dan periode magang perlu diisi sebelum sertifikat dapat ditandai terbit.',
                    ]);
                }
            }

            if (array_key_exists('placementStatus', $data)) {
                if ($applicant->study_status !== 'lulus') {
                    throw ValidationException::withMessages([
                        'placementStatus' => 'Status penempatan alumni hanya dapat diubah setelah mahasiswa berstatus lulus.',
                    ]);
                }
                $applicant->placement()->updateOrCreate(
                    ['scholarship_applicant_id' => $applicant->id],
                    ['status' => $data['placementStatus']]
                );
            } elseif (($data['studyStatus'] ?? null) === 'lulus' && ! $applicant->placement()->exists()) {
                $applicant->placement()->create(['status' => 'belum_ditempatkan']);
            }
        });

        return response()->json($this->serializeApplicant(
            $applicant->fresh()->load(['campus', 'program', 'academicRecords', 'fundingRecords', 'internships', 'placement'])
        ));
    }

    private function applicantRules(): array
    {
        return [
            'registrationNumber' => ['required', 'string', 'min:4', 'max:30', 'unique:scholarship_applicants,registration_number'],
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'gender' => ['required', 'in:L,P'],
            'birthDate' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'schoolName' => ['required', 'string', 'min:2', 'max:180'],
            'schoolType' => ['required', 'in:SMA,SMK,MA'],
            'province' => ['required', 'string', 'min:2', 'max:100'],
            'regency' => ['required', 'string', 'min:2', 'max:100'],
            'selectionPath' => ['required', 'in:'.implode(',', self::SELECTION_PATHS)],
            'selectionStatus' => ['required', 'in:mendaftar'],
            'campus' => ['required', 'string', 'min:2', 'max:180'],
            'program' => ['required', 'string', 'min:2', 'max:180'],
            'degree' => ['required', 'in:D1,D2,D3,D4/S1'],
            'cohort' => ['required', 'integer', 'min:2000', 'max:2100'],
        ];
    }

    private function serializeApplicant(ScholarshipApplicant $applicant): array
    {
        $internship = $applicant->internships->sortByDesc('id')->first();
        $funding = $applicant->fundingRecords->groupBy('semester');
        $totals = $applicant->fundingRecords->groupBy('funding_type')
            ->map(fn ($records) => (float) $records->sum('amount'));
        $stipend = (float) ($totals['stipend'] ?? $totals['stipend_books'] ?? 0);
        $books = (float) ($totals['books'] ?? 0);
        $placementStatus = $applicant->placement?->status
            ?? ($applicant->study_status === 'lulus' ? 'belum_ditempatkan' : 'belum_lulus');

        return [
            'id' => $applicant->id,
            'registrationNumber' => $applicant->registration_number,
            'name' => $applicant->name,
            'gender' => $applicant->gender,
            'birthDate' => $applicant->birth_date->format('Y-m-d'),
            'ageAtRegistration' => $this->ageAtRegistration($applicant),
            'ageEligible' => $this->ageAtRegistration($applicant) <= 23,
            'schoolName' => $applicant->school_name,
            'schoolType' => $applicant->school_type,
            'province' => $applicant->province,
            'regency' => $applicant->regency,
            'selectionPath' => $applicant->selection_path,
            'selectionStatus' => $applicant->selection_status,
            'campus' => $applicant->campus->name,
            'program' => $applicant->program->name,
            'degree' => $applicant->program->degree,
            'cohort' => (int) $applicant->cohort,
            'gpa' => $applicant->latest_gpa,
            'activeSemester' => $applicant->active_semester,
            'studyStatus' => $applicant->study_status,
            'tuitionPaid' => (float) ($totals['tuition'] ?? 0),
            'stipendAndBooks' => $stipend + $books,
            'stipend' => $stipend,
            'books' => $books,
            'transport' => (float) ($totals['transport'] ?? 0),
            'graduationCost' => (float) ($totals['graduation'] ?? 0),
            'graduationDate' => $applicant->graduation_date?->format('Y-m-d'),
            'studyDurationMonths' => $applicant->study_duration_months,
            'internshipLocation' => $internship?->location,
            'internshipPeriod' => $internship?->period,
            'certificateIssued' => (bool) ($internship?->certificate_issued ?? false),
            'placementStatus' => $placementStatus,
            'academicRecords' => $applicant->academicRecords
                ->sortBy('semester')
                ->map(fn (AcademicSemesterRecord $record) => [
                    'semester' => (int) $record->semester,
                    'ip' => (float) $record->ip,
                ])->values(),
            'fundingBySemester' => $funding->map(function ($records, $semester): array {
                $amounts = $records->keyBy('funding_type')->map(fn (SemesterFunding $record) => (float) $record->amount);

                return [
                    'semester' => (int) $semester,
                    'tuitionPaid' => (float) ($amounts['tuition'] ?? 0),
                    'stipendAndBooks' => (float) ($amounts['stipend'] ?? $amounts['stipend_books'] ?? 0) + (float) ($amounts['books'] ?? 0),
                    'stipend' => (float) ($amounts['stipend'] ?? $amounts['stipend_books'] ?? 0),
                    'books' => (float) ($amounts['books'] ?? 0),
                    'transport' => (float) ($amounts['transport'] ?? 0),
                    'graduationCost' => (float) ($amounts['graduation'] ?? 0),
                ];
            })->sortKeys()->values(),
        ];
    }

    private function replaceAcademicRecords(ScholarshipApplicant $applicant, array $records): void
    {
        $semesters = collect($records)->pluck('semester');
        if ($semesters->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['academicRecords' => 'Setiap semester hanya boleh memiliki satu nilai IP.']);
        }
        $query = $applicant->academicRecords();
        $records === [] ? $query->delete() : $query->whereNotIn('semester', $semesters)->delete();
        foreach ($records as $record) {
            AcademicSemesterRecord::updateOrCreate(
                ['scholarship_applicant_id' => $applicant->id, 'semester' => $record['semester']],
                ['ip' => $record['ip']]
            );
        }
    }

    private function replaceFundingRecords(ScholarshipApplicant $applicant, array $records): void
    {
        $semesters = collect($records)->pluck('semester');
        if ($semesters->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['fundingBySemester' => 'Setiap semester hanya boleh memiliki satu rincian pendanaan.']);
        }
        $query = $applicant->fundingRecords();
        $records === [] ? $query->delete() : $query->whereNotIn('semester', $semesters)->delete();

        $types = [
            'tuitionPaid' => 'tuition',
            'transport' => 'transport',
            'graduationCost' => 'graduation',
        ];
        foreach ($records as $record) {
            foreach ($types as $key => $type) {
                SemesterFunding::updateOrCreate(
                    [
                        'scholarship_applicant_id' => $applicant->id,
                        'semester' => $record['semester'],
                        'funding_type' => $type,
                    ],
                    ['amount' => $record[$key]]
                );
            }
            foreach ([
                'stipend' => $record['stipend'] ?? $record['stipendAndBooks'],
                'books' => $record['books'] ?? 0,
            ] as $type => $amount) {
                SemesterFunding::updateOrCreate(
                    [
                        'scholarship_applicant_id' => $applicant->id,
                        'semester' => $record['semester'],
                        'funding_type' => $type,
                    ],
                    ['amount' => $amount]
                );
            }
        }
    }

    private function assertSelectionTransition(ScholarshipApplicant $applicant, string $nextStatus): void
    {
        $current = $applicant->selection_status;
        if ($current === $nextStatus) {
            return;
        }
        if ($current === 'mengundurkan_diri') {
            throw ValidationException::withMessages(['selectionStatus' => 'Pendaftar yang mengundurkan diri tidak dapat dibuka kembali.']);
        }
        if ($nextStatus === 'mengundurkan_diri') {
            return;
        }
        $currentIndex = array_search($current, self::SELECTION_STATUSES, true);
        $nextIndex = array_search($nextStatus, self::SELECTION_STATUSES, true);
        if ($currentIndex === false || $nextIndex === false || $nextIndex !== $currentIndex + 1) {
            throw ValidationException::withMessages([
                'selectionStatus' => 'Tahap seleksi hanya dapat maju satu tahap. Urutan: mendaftar → lolos administrasi → lolos tes → diterima.',
            ]);
        }
        $this->assertAgeEligibleForSelection($this->ageAtRegistration($applicant), $nextStatus);
    }

    private function assertAgeEligibleForSelection(int $age, string $status): void
    {
        if ($age > 23 && ! in_array($status, ['mendaftar', 'mengundurkan_diri'], true)) {
            throw ValidationException::withMessages([
                'birthDate' => 'Syarat usia maksimal 23 tahun tidak terpenuhi pada tanggal pendaftaran.',
            ]);
        }
    }

    private function assertQuotaAvailable(int $programId, int $cohort, ?int $ignoreApplicantId = null): void
    {
        StudyProgram::whereKey($programId)->lockForUpdate()->firstOrFail();
        $allocation = ProgramQuota::where('study_program_id', $programId)
            ->where('cohort', $cohort)
            ->lockForUpdate()
            ->first();
        if (! $allocation) {
            throw ValidationException::withMessages([
                'selectionStatus' => 'Kuota belum ditetapkan untuk prodi dan angkatan ini.',
            ]);
        }
        $occupied = ScholarshipApplicant::where('study_program_id', $programId)
            ->where('cohort', $cohort)
            ->where('selection_status', 'diterima')
            ->when($ignoreApplicantId, fn (Builder $query) => $query->whereKeyNot($ignoreApplicantId))
            ->count();
        if ($occupied >= $allocation->quota) {
            throw ValidationException::withMessages([
                'selectionStatus' => "Kuota prodi sudah penuh ({$occupied}/{$allocation->quota}).",
            ]);
        }
    }

    private function ageAtRegistration(ScholarshipApplicant $applicant): int
    {
        return $this->ageOn($applicant->birth_date, $applicant->registered_at);
    }

    private function ageOn(string|Carbon $birthDate, string|Carbon $at): int
    {
        return max(0, (int) Carbon::parse($birthDate)->diffInYears(Carbon::parse($at), false));
    }

    private function percentage(int|float $numerator, int|float $denominator): float
    {
        return $denominator > 0 ? round(($numerator / $denominator) * 100, 1) : 0.0;
    }
}
