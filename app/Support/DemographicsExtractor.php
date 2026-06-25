<?php

namespace App\Support;

class DemographicsExtractor
{
    // Labels that belong to each product category. Used to filter the
    // Health Conditions chart so irrelevant conditions don't appear
    // (e.g. Stroke shouldn't show up on a skin product's chart).
    private static array $CATEGORY_LABELS = [
        'eye'   => ['Pterygium', 'Glaucoma', 'Cataract', 'Dry Eyes', 'Teary Eyes', 'Blurry Vision', 'Eye Irritation', 'Itchiness', 'Eye Redness', 'Eye Floaters'],
        'ear'   => ['Ear Pain', 'Ear Swelling', 'Ear Blockage', 'Tinnitus', 'Perforated Eardrum', 'Ear Discharge', 'Difficulty Hearing', 'Ear Itchiness', 'Ear Infection', 'Vertigo'],
        'nasal' => ['Allergic Rhinitis', 'Sinusitis', 'Nasal Polyps'],
        'skin'  => ['Melasma', 'Dark Spots', 'Freckles', 'Skin Scars', 'Acne/Pimples', 'Eczema', 'Psoriasis', 'Hyperpigmentation', 'Blemishes'],
    ];

    // Ordered keyword → category map. More-specific phrases come first so they
    // win over shorter overlapping ones (e.g. 'haplunas heal' before 'haplunas').
    private static array $PRODUCT_CATEGORY_MAP = [
        // Eye / Vision
        'clear sight'      => 'eye',
        'clearsight'       => 'eye',
        'blueberry eye'    => 'eye',
        'eyevera'          => 'eye',
        'eyevive'          => 'eye',
        'glaucofree'       => 'eye',
        'lumieyes'         => 'eye',
        'lucent eye'       => 'eye',
        'haplunas heal'    => 'eye',   // "Haplunas Healing Eye Cream" — before generic 'haplunas'
        'haplunas eye'     => 'eye',
        'ptery'            => 'eye',   // PteryFix, Ptery Clear, Pterygium
        'vision pro'       => 'eye',
        'visionex'         => 'eye',
        'furvision'        => 'eye',
        'ocupet'           => 'eye',
        'nano 10x'         => 'eye',
        // Ear / Hearing
        'audicure'         => 'ear',
        'ear relief'       => 'ear',
        'hearwell'         => 'ear',
        // Nasal / Sinus
        'haplunas'         => 'nasal', // Haplunas Balm (generic fallback after eye variants above)
        'sinusitis'        => 'nasal',
        'sinuvex'          => 'nasal',
        'sinuxyl'          => 'nasal',
        'nasal'            => 'nasal',
        // Skin / Whitening
        'ginseng'          => 'skin',
        'ginsera'          => 'skin',
        'belo'             => 'skin',
        'niacinamide'      => 'skin',
        'whitening'        => 'skin',
        'dermorepubliq'    => 'skin',
        'dragon blood'     => 'skin',
        'retinol'          => 'skin',
        'scar cream'       => 'skin',
        'turmeric soap'    => 'skin',
        'vitamin c toner'  => 'skin',
        'vc toner'         => 'skin',
        'lumicare'         => 'skin',
        'sunscreen'        => 'skin',
        'rose body'        => 'skin',
        'castor oil'       => 'skin',
        'acai berry'       => 'skin',
    ];

    // Returns the product category keyword (eye/ear/nasal/skin/general).
    public static function categoryForProduct(string $product): string
    {
        $lower = strtolower($product);
        foreach (self::$PRODUCT_CATEGORY_MAP as $keyword => $category) {
            if (str_contains($lower, $keyword)) {
                return $category;
            }
        }
        return 'general';
    }

    // Returns the allowed condition labels for a category, or null for 'general' (no filter).
    public static function labelsForCategory(string $category): ?array
    {
        return self::$CATEGORY_LABELS[$category] ?? null;
    }

    private static array $CONDITIONS = [
        // Nasal / Sinus — listed first so sinusitis patients don't get
        // captured by secondary eye symptoms (e.g. "nagluluha" from sneezing)
        'ALLERGIC RHINITIS' => 'Allergic Rhinitis',
        'RHINITIS'          => 'Allergic Rhinitis',
        'SINUSITIS'         => 'Sinusitis',
        'NASAL POLYPS'      => 'Nasal Polyps',
        'POLYPS'            => 'Nasal Polyps',

        // Eye conditions
        'PTERYGIUM'         => 'Pterygium',
        'POGITA'            => 'Pterygium',        // Filipino slang
        'PUGITA'            => 'Pterygium',        // Filipino alternate spelling
        'GLAUCOMA'          => 'Glaucoma',
        'CATARACT'          => 'Cataract',
        'KATARATA'          => 'Cataract',        // Filipino word
        'DRY EYE'           => 'Dry Eyes',
        'DRYNESS'           => 'Dry Eyes',
        'LUHA'              => 'Teary Eyes',
        'MALABO'            => 'Blurry Vision',
        'HAPDI'             => 'Eye Irritation',  // burning/stinging sensation
        'NANGANGATI'        => 'Itchiness',
        'NAMUMULA'          => 'Eye Redness',     // Filipino: reddening
        'PAMUMULA'          => 'Eye Redness',     // Filipino: redness
        'FLOATERS'          => 'Eye Floaters',

        // Hearing / Ear — common AudiCure symptoms
        'SUMASAKIT ANG TENGA' => 'Ear Pain',
        'MASAKIT ANG TENGA'   => 'Ear Pain',
        'PANANAKIT NG TENGA'  => 'Ear Pain',
        'EAR PAIN'            => 'Ear Pain',
        'NAMAMAGA ANG TENGA'  => 'Ear Swelling',
        'NAMAGA ANG TENGA'    => 'Ear Swelling',
        'BARA ANG TENGA'      => 'Ear Blockage',      // blocked/clogged ear
        'BARA SA TENGA'       => 'Ear Blockage',
        'BARADO ANG TENGA'    => 'Ear Blockage',
        'BLOCKED EAR'         => 'Ear Blockage',
        'CLOGGED EAR'         => 'Ear Blockage',
        'UMUUGONG'            => 'Tinnitus',           // Filipino: ear ringing/buzzing
        'MAUGONG'             => 'Tinnitus',
        'UGONG'               => 'Tinnitus',
        'UMALINGAWNGAW'       => 'Tinnitus',
        'ALINGAWNGAW'         => 'Tinnitus',
        'TINNITUS'            => 'Tinnitus',
        'BUTAS ANG'           => 'Perforated Eardrum', // butas ang tenga / butas ang eardrum
        'EARDRUM'             => 'Perforated Eardrum',
        'PERFORATED'          => 'Perforated Eardrum',
        'LUMALABAS SA TENGA'  => 'Ear Discharge',
        'HINDI MAKADINIG'     => 'Difficulty Hearing',
        'HINDI MARINIG'       => 'Difficulty Hearing',
        'MAHIRAP MAKARINIG'   => 'Difficulty Hearing',
        'HEARING LOSS'        => 'Difficulty Hearing',
        'DEAF'                => 'Difficulty Hearing',
        'BINGI'               => 'Difficulty Hearing',  // Filipino: can't hear well
        'KUMAKATI ANG TENGA'  => 'Ear Itchiness',
        'MAKATING ANG TENGA'  => 'Ear Itchiness',
        'KATI ANG TENGA'      => 'Ear Itchiness',
        'OTITIS'              => 'Ear Infection',
        'EAR INFECTION'       => 'Ear Infection',
        'VERTIGO'             => 'Vertigo',

        // Skin — common Ginseng Serum / whitening product symptoms
        'MELASMA'            => 'Melasma',
        'DARK SPOT'          => 'Dark Spots',
        'DARKSPOT'           => 'Dark Spots',
        'AGE SPOT'           => 'Dark Spots',
        'SUN SPOT'           => 'Dark Spots',
        'PEKAS'              => 'Freckles',            // Filipino: freckles
        'FRECKLE'            => 'Freckles',
        'PEKLAT'             => 'Skin Scars',          // Filipino: scars
        'SCAR'               => 'Skin Scars',
        'ACNE'               => 'Acne/Pimples',
        'PIMPLE'             => 'Acne/Pimples',
        'TIGYAWAT'           => 'Acne/Pimples',        // Filipino: pimples
        'ECZEMA'             => 'Eczema',
        'PSORIASIS'          => 'Psoriasis',
        'HYPERPIGMENTATION'  => 'Hyperpigmentation',
        'PIGMENTATION'       => 'Hyperpigmentation',
        'BLEMISH'            => 'Blemishes',

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
