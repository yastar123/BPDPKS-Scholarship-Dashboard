<?php

namespace Database\Seeders;

use App\Models\AcademicSemesterRecord;
use App\Models\AlumniPlacement;
use App\Models\BudgetAllocation;
use App\Models\Campus;
use App\Models\Internship;
use App\Models\ProgramQuota;
use App\Models\ScholarshipApplicant;
use App\Models\SemesterFunding;
use App\Models\StudyProgram;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (ScholarshipApplicant::exists()) {
            return;
        }

        $riau = Campus::firstOrCreate(
            ['name' => 'Kampus Contoh Riau — Data Demo'],
            ['province' => 'Riau']
        );
        $kalimantan = Campus::firstOrCreate(
            ['name' => 'Kampus Contoh Kalimantan — Data Demo'],
            ['province' => 'Kalimantan Barat']
        );
        $programs = [
            'budidaya' => StudyProgram::firstOrCreate([
                'campus_id' => $riau->id,
                'name' => 'Budidaya Tanaman Perkebunan',
                'degree' => 'D3',
            ]),
            'pengolahan' => StudyProgram::firstOrCreate([
                'campus_id' => $riau->id,
                'name' => 'Teknologi Pengolahan Hasil Perkebunan',
                'degree' => 'D3',
            ]),
            'manajemen' => StudyProgram::firstOrCreate([
                'campus_id' => $kalimantan->id,
                'name' => 'Manajemen Perkebunan',
                'degree' => 'D4/S1',
            ]),
        ];

        foreach ([
            [$programs['budidaya'], 2026, 3, 300000000],
            [$programs['pengolahan'], 2026, 2, 250000000],
            [$programs['manajemen'], 2026, 3, 320000000],
            [$programs['budidaya'], 2025, 4, 360000000],
            [$programs['manajemen'], 2025, 3, 320000000],
            [$programs['budidaya'], 2024, 3, 300000000],
        ] as [$program, $cohort, $quota, $budget]) {
            ProgramQuota::firstOrCreate(
                ['study_program_id' => $program->id, 'cohort' => $cohort],
                ['quota' => $quota]
            );
            BudgetAllocation::firstOrCreate(
                ['study_program_id' => $program->id, 'cohort' => $cohort],
                ['amount' => $budget]
            );
        }

        $students = [
            ['DEMO-26-0001', 'Pendaftar Demo 001', 'P', '2005-02-14', 'SMK Perkebunan Contoh', 'SMK', 'Riau', 'Kampar', 'pekebun', 'diterima', 'budidaya', 2026, 3.48, 3, 'aktif', '2026-05-10'],
            ['DEMO-26-0002', 'Pendaftar Demo 002', 'L', '2004-11-03', 'SMA Negeri Contoh 1', 'SMA', 'Riau', 'Pelalawan', 'keluarga_pekebun', 'diterima', 'budidaya', 2026, 2.61, 3, 'aktif', '2026-05-11'],
            ['DEMO-26-0003', 'Pendaftar Demo 003', 'P', '2005-08-27', 'MA Contoh Sejahtera', 'MA', 'Kalimantan Barat', 'Sanggau', 'penyuluh', 'diterima', 'manajemen', 2026, 3.21, 2, 'aktif', '2026-05-12'],
            ['DEMO-26-0004', 'Pendaftar Demo 004', 'L', '2004-04-19', 'SMK Agribisnis Contoh', 'SMK', 'Jambi', 'Tanjung Jabung Barat', 'karyawan_sawit', 'diterima', 'pengolahan', 2026, 3.72, 2, 'aktif', '2026-05-13'],
            ['DEMO-26-0005', 'Pendaftar Demo 005', 'L', '2005-12-06', 'SMA Negeri Contoh 2', 'SMA', 'Riau', 'Indragiri Hulu', 'keluarga_karyawan', 'diterima', 'pengolahan', 2026, 2.10, 4, 'putus', '2026-05-14'],
            ['DEMO-26-0006', 'Pendaftar Demo 006', 'P', '2006-03-11', 'MA Contoh Mandiri', 'MA', 'Sumatera Utara', 'Labuhanbatu', 'pengurus_asosiasi', 'lolos_tes', 'budidaya', 2026, null, null, 'aktif', '2026-05-15'],
            ['DEMO-26-0007', 'Pendaftar Demo 007', 'L', '2005-07-21', 'SMK Negeri Contoh 3', 'SMK', 'Kalimantan Tengah', 'Kotawaringin Timur', 'asn', 'mengundurkan_diri', 'manajemen', 2026, null, null, 'aktif', '2026-05-16'],
            ['DEMO-26-0008', 'Pendaftar Demo 008', 'P', '1999-10-01', 'SMA Negeri Contoh 4', 'SMA', 'Jambi', 'Muaro Jambi', 'pekebun', 'mendaftar', 'budidaya', 2026, null, null, 'aktif', '2026-05-17'],
            ['DEMO-25-0001', 'Pendaftar Demo 009', 'P', '2003-06-18', 'SMK Perkebunan Contoh', 'SMK', 'Riau', 'Siak', 'keluarga_pekebun', 'diterima', 'budidaya', 2025, 3.55, 5, 'lulus', '2025-05-10'],
            ['DEMO-25-0002', 'Pendaftar Demo 010', 'L', '2004-01-30', 'SMA Negeri Contoh 5', 'SMA', 'Kalimantan Barat', 'Ketapang', 'karyawan_sawit', 'diterima', 'manajemen', 2025, 3.08, 4, 'aktif', '2025-05-11'],
            ['DEMO-25-0003', 'Pendaftar Demo 011', 'P', '2003-09-25', 'MA Contoh Insan', 'MA', 'Jambi', 'Batanghari', 'penyuluh', 'diterima', 'manajemen', 2025, 2.24, 5, 'putus', '2025-05-12'],
            ['DEMO-24-0001', 'Pendaftar Demo 012', 'L', '2002-03-16', 'SMK Agribisnis Contoh', 'SMK', 'Riau', 'Bengkalis', 'pengurus_asosiasi', 'diterima', 'budidaya', 2024, 3.67, 6, 'lulus', '2024-05-10'],
        ];

        foreach ($students as $student) {
            [$registrationNumber, $name, $gender, $birthDate, $schoolName, $schoolType,
                $province, $regency, $path, $selectionStatus, $programKey, $cohort,
                $gpa, $semester, $studyStatus, $registeredAt] = $student;
            $program = $programs[$programKey];
            $applicant = ScholarshipApplicant::create([
                'registration_number' => $registrationNumber,
                'name' => $name,
                'gender' => $gender,
                'birth_date' => $birthDate,
                'school_name' => $schoolName,
                'school_type' => $schoolType,
                'province' => $province,
                'regency' => $regency,
                'selection_path' => $path,
                'selection_status' => $selectionStatus,
                'campus_id' => $program->campus_id,
                'study_program_id' => $program->id,
                'cohort' => $cohort,
                'latest_gpa' => $gpa,
                'active_semester' => $semester,
                'study_status' => $studyStatus,
                'graduation_date' => $studyStatus === 'lulus' ? ($cohort === 2025 ? '2026-06-30' : '2024-12-20') : null,
                'study_duration_months' => $studyStatus === 'lulus' ? ($cohort === 2025 ? 24 : 30) : null,
                'registered_at' => $registeredAt,
            ]);
            if ($selectionStatus !== 'diterima') {
                continue;
            }

            $recordedSemesters = min((int) $semester, $studyStatus === 'lulus' ? 6 : 3);
            for ($term = 1; $term <= $recordedSemesters; $term++) {
                $ip = max(1.8, min(3.95, (float) $gpa + (($term % 2) ? -0.08 : 0.11)));
                AcademicSemesterRecord::create([
                    'scholarship_applicant_id' => $applicant->id,
                    'semester' => $term,
                    'ip' => round($ip, 2),
                ]);
                foreach ([
                    'tuition' => 7500000,
                    'stipend' => 1400000,
                    'books' => 250000,
                    'transport' => 450000,
                    'graduation' => $studyStatus === 'lulus' && $term === $recordedSemesters ? 3500000 : 0,
                ] as $type => $amount) {
                    SemesterFunding::create([
                        'scholarship_applicant_id' => $applicant->id,
                        'semester' => $term,
                        'funding_type' => $type,
                        'amount' => $amount,
                    ]);
                }
            }
            if (in_array($registrationNumber, ['DEMO-26-0001', 'DEMO-26-0003', 'DEMO-25-0002'], true)) {
                Internship::create([
                    'scholarship_applicant_id' => $applicant->id,
                    'location' => 'Kebun Praktik Contoh, '.$province,
                    'period' => 'Juni–Juli '.$cohort,
                    'certificate_issued' => $registrationNumber !== 'DEMO-26-0003',
                ]);
            }
            if ($studyStatus === 'lulus') {
                AlumniPlacement::create([
                    'scholarship_applicant_id' => $applicant->id,
                    'status' => $registrationNumber === 'DEMO-25-0001' ? 'bekerja' : 'belum_ditempatkan',
                    'organization' => $registrationNumber === 'DEMO-25-0001' ? 'Mitra Perkebunan Contoh' : null,
                    'placed_at' => $registrationNumber === 'DEMO-25-0001' ? '2026-02-15' : null,
                ]);
            }
        }
    }
}
