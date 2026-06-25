<?php

namespace App\Student\Services;

use App\Student\Models\Certificate;
use App\Student\Models\IssuedCertificate;

class CertificateCodeService
{
    public function nextCode(Certificate $certificate): string
    {
        $year = now()->year;
        $issuedCount = IssuedCertificate::query()
            ->where('certificate_id', $certificate->id)
            ->whereYear('creation_time', $year)
            ->count();

        $sequence = $certificate->sequence_start + $issuedCount;
        $suffix = $certificate->sequence_suffix ?? 'R';

        return sprintf('CDE N° %05d - %d R.A./UPRIT - %s', $sequence, $year, $suffix);
    }
}
