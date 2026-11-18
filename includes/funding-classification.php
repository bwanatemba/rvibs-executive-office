<?php

const FUNDING_TYPES = [
    'grant' => 'Grant',
    'equipment_donation' => 'Equipment donation',
    'staff_exchange' => 'Staff exchange',
    'student_exchange' => 'Student exchange',
    'partnership' => 'Partnership',
    'service_contract' => 'Service contract or tender',
    'matching_grant' => 'Matching grant',
    'challenge_prize' => 'Challenge fund or prize',
    'scholarship_sponsorship' => 'Scholarship or sponsorship',
    'impact_investment' => 'Equity or impact investment',
    'concessional_loan' => 'Concessional loan',
    'public_private_partnership' => 'Public-private partnership',
    'in_kind_support' => 'In-kind support',
];

const FUNDING_APPLICANTS = [
    'RVIBS' => 'RVIBS',
    'ATS' => 'ATS',
    'joint' => 'Joint RVIBS–ATS',
];

const FUNDING_CATEGORIES = [
    'TVET and vocational education',
    'Digital skills and ICT training',
    'Youth employment and employability',
    'Entrepreneurship and MSME development',
    'Innovation and technology development',
    'Startup incubation and acceleration',
    'STEM and robotics education',
    'EdTech and digital learning',
    'Women and girls economic empowerment',
    'International education partnerships and mobility',
    'Green skills and climate innovation',
    'Research, development and applied innovation',
    'Infrastructure and equipment',
    'Scholarships and training sponsorships',
    'Corporate social responsibility',
    'Government and donor-funded service contracts',
    'Inclusive education and disability',
    'Social enterprise and impact investment',
    'Private-sector development and matching grants',
    'Community development and livelihoods',
];

function funding_needs_eligibility_check(PDO $db, int $opportunityId): bool
{
    $stmt = $db->prepare("SELECT fo.discovery_key,
        (SELECT result FROM funding_eligibility_checks
         WHERE opportunity_id = fo.id AND check_type = 'eligibility'
         ORDER BY checked_at DESC, id DESC LIMIT 1) AS eligibility_result
        FROM funding_opportunities fo WHERE fo.id = ?");
    $stmt->execute([$opportunityId]);
    $opportunity = $stmt->fetch();
    return !empty($opportunity['discovery_key']) && $opportunity['eligibility_result'] !== 'pass';
}
