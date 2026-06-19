<?php

namespace App\Support;

class DemographicsExtractor
{
    private static array $CONDITIONS = [
        'GLAUCOMA'     => 'Glaucoma',
        'CATARACT'     => 'Cataract',
        'SINUSITIS'    => 'Sinusitis',
        'DIABETES'     => 'Diabetes',
        'DIABETIC'     => 'Diabetes',
        'HYPERTENSION' => 'Hypertension',
        'HIGH BLOOD'   => 'Hypertension',
        'DRY EYE'      => 'Dry Eyes',
        'DRYNESS'      => 'Dry Eyes',
        'LUHA'         => 'Dry Eyes',
        'MALABO'       => 'Blurry Vision',
        'ARTHRITIS'    => 'Arthritis',
        'ASTHMA'       => 'Asthma',
        'CHOLESTEROL'  => 'High Cholesterol',
        'COLESTEROL'   => 'High Cholesterol',
        'URIC ACID'    => 'Uric Acid',
        'KIDNEY'       => 'Kidney',
        'THYROID'      => 'Thyroid',
        'CANCER'       => 'Cancer',
        'JOINT PAIN'   => 'Joint Pain',
        'OSTEOPOROSIS' => 'Osteoporosis',
        'MIGRAINE'     => 'Migraine',
    ];

    public static function age(?string $note): ?int
    {
        if (!$note) return null;
        if (preg_match('/(\d{2,3})\s*(?:years?|yrs?\.?)\s*old/i', $note, $m)) {
            $age = (int) $m[1];
            return ($age >= 18 && $age <= 100) ? $age : null;
        }
        return null;
    }

    public static function condition(?string $note): ?string
    {
        if (!$note) return null;
        $upper = strtoupper($note);
        foreach (self::$CONDITIONS as $keyword => $label) {
            if (str_contains($upper, $keyword)) {
                return $label;
            }
        }
        return null;
    }
}
