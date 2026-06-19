<?php

namespace App\Support;

class DemographicsExtractor
{
    private static array $CONDITIONS = [
        // Nasal / Sinus — listed first so sinusitis patients don't get
        // captured by secondary eye symptoms (e.g. "nagluluha" from sneezing)
        'ALLERGIC RHINITIS' => 'Allergic Rhinitis',
        'RHINITIS'          => 'Allergic Rhinitis',
        'SINUSITIS'         => 'Sinusitis',
        'NASAL POLYPS'      => 'Nasal Polyps',
        'POLYPS'            => 'Nasal Polyps',

        // Eye conditions
        'GLAUCOMA'          => 'Glaucoma',
        'CATARACT'          => 'Cataract',
        'KATARATA'          => 'Cataract',        // Filipino word
        'DRY EYE'           => 'Dry Eyes',
        'DRYNESS'           => 'Dry Eyes',
        'LUHA'              => 'Teary Eyes',
        'MALABO'            => 'Blurry Vision',
        'HAPDI'             => 'Eye Irritation',   // burning/stinging sensation

        // Neurological / Stroke
        'STROKE'            => 'Stroke',
        'PAMAMANHID'        => 'Numbness',
        'MANHID'            => 'Numbness',

        // Metabolic
        'DIABETES'          => 'Diabetes',
        'DIABETIC'          => 'Diabetes',
        'URIC ACID'         => 'Uric Acid',
        'GOUT'              => 'Gout',
        'CHOLESTEROL'       => 'High Cholesterol',
        'COLESTEROL'        => 'High Cholesterol',

        // Cardiovascular
        'HYPERTENSION'      => 'Hypertension',
        'HIGHBLOOD'         => 'Hypertension',    // no-space variant
        'HIGH BLOOD'        => 'Hypertension',
        'PRESYON'           => 'Hypertension',    // Filipino

        // Musculoskeletal
        'ARTHRITIS'         => 'Arthritis',
        'JOINT PAIN'        => 'Joint Pain',
        'OSTEOPOROSIS'      => 'Osteoporosis',

        // Respiratory
        'ASTHMA'            => 'Asthma',
        'ACID REFLUX'       => 'Acid Reflux',

        // Other
        'DIALYSIS'          => 'Kidney Disease',
        'KIDNEY'            => 'Kidney Disease',
        'THYROID'           => 'Thyroid',
        'CANCER'            => 'Cancer',
        'MIGRAINE'          => 'Migraine',
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

    /**
     * Returns every condition label that matches the note (deduplicated, ordered by keyword priority).
     * Nasal conditions still come first to prevent sinusitis patients from matching LUHA (Teary Eyes).
     */
    public static function conditions(?string $note): array
    {
        if (!$note) return [];
        $upper  = strtoupper($note);
        $labels = [];
        foreach (self::$CONDITIONS as $keyword => $label) {
            if (str_contains($upper, $keyword) && !in_array($label, $labels, true)) {
                $labels[] = $label;
            }
        }
        return $labels;
    }

    /** @deprecated Use conditions() — returns first match only for backward compat. */
    public static function condition(?string $note): ?string
    {
        return self::conditions($note)[0] ?? null;
    }
}
