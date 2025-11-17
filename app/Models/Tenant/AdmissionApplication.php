<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Tenant\Division;
use App\Models\Tenant\District;
use App\Models\Tenant\Area;

class AdmissionApplication extends Model
{
    protected $connection = 'tenant';

    protected $table = 'admission_applications';

    protected $fillable = [
        'application_no',
        'academic_session_id',
        'session_grade_id',
        'application_type',
        'existing_student_id',

        'applicant_name',
        'gender',
        'date_of_birth',
        'student_phone',
        'student_email',

        'father_name',
        'father_phone',
        'father_occupation',
        'mother_name',
        'mother_phone',
        'mother_occupation',

        'guardian_type',
        'guardian_name',
        'guardian_phone',
        'guardian_relation',

        'present_address',
        'permanent_address',
        'is_present_same_as_permanent',

        'previous_institution_name',
        'previous_class',
        'previous_result',
        'previous_result_division',

        'residential_type',
        'applied_via',
        'application_date',

        'status',
        'status_note',
        'admitted_student_id',

        'photo_path',
        'meta_json',
    ];

    protected $casts = [
        'present_address'              => 'array',
        'permanent_address'            => 'array',
        'meta_json'                    => 'array',
        'is_present_same_as_permanent' => 'boolean',
        'application_date'             => 'date',
        'date_of_birth'                => 'date',
    ];

    /**
     * Extra attributes to include in JSON.
     */
    protected $appends = [
        'formatted_present_address',
        'formatted_permanent_address',

        'present_division_name',
        'present_district_name',
        'present_area_name',

        'permanent_division_name',
        'permanent_district_name',
        'permanent_area_name',
    ];

    /* -------- Relationships -------- */

    public function session(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

    public function sessionGrade(): BelongsTo
    {
        return $this->belongsTo(SessionGrade::class, 'session_grade_id');
    }

    public function existingStudent(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'existing_student_id');
    }

    public function admittedStudent(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'admitted_student_id');
    }

    /* -------- Address Helper Methods -------- */

    /**
     * Get present address division model.
     */
    public function presentDivision(): ?Division
    {
        $divisionId = $this->present_address['division_id'] ?? null;

        return $divisionId ? Division::find($divisionId) : null;
    }

    /**
     * Get present address district model.
     */
    public function presentDistrict(): ?District
    {
        $districtId = $this->present_address['district_id'] ?? null;

        return $districtId ? District::find($districtId) : null;
    }

    /**
     * Get present address area model.
     */
    public function presentArea(): ?Area
    {
        $areaId = $this->present_address['area_id'] ?? null;

        return $areaId ? Area::find($areaId) : null;
    }

    /**
     * Get permanent address division model.
     */
    public function permanentDivision(): ?Division
    {
        $divisionId = $this->permanent_address['division_id'] ?? null;

        return $divisionId ? Division::find($divisionId) : null;
    }

    /**
     * Get permanent address district model.
     */
    public function permanentDistrict(): ?District
    {
        $districtId = $this->permanent_address['district_id'] ?? null;

        return $districtId ? District::find($districtId) : null;
    }

    /**
     * Get permanent address area model.
     */
    public function permanentArea(): ?Area
    {
        $areaId = $this->permanent_address['area_id'] ?? null;

        return $areaId ? Area::find($areaId) : null;
    }

    /* -------- Formatted Address Accessors -------- */

    /**
     * Get formatted present address as string.
     */
    public function getFormattedPresentAddressAttribute(): ?string
    {
        if (!$this->present_address) {
            return null;
        }

        $parts = [];

        if ($village = $this->present_address['village_house_holding'] ?? null) {
            $parts[] = $village;
        }

        if ($area = $this->presentArea()) {
            $parts[] = $area->name;
        }

        if ($district = $this->presentDistrict()) {
            $parts[] = $district->name;
        }

        if ($division = $this->presentDivision()) {
            $parts[] = $division->name;
        }

        return implode(', ', $parts);
    }

    /**
     * Get formatted permanent address as string.
     */
    public function getFormattedPermanentAddressAttribute(): ?string
    {
        if (!$this->permanent_address) {
            return null;
        }

        $parts = [];

        if ($village = $this->permanent_address['village_house_holding'] ?? null) {
            $parts[] = $village;
        }

        if ($area = $this->permanentArea()) {
            $parts[] = $area->name;
        }

        if ($district = $this->permanentDistrict()) {
            $parts[] = $district->name;
        }

        if ($division = $this->permanentDivision()) {
            $parts[] = $division->name;
        }

        return implode(', ', $parts);
    }

    /* -------- Name Accessors (for frontend convenience) -------- */

    public function getPresentDivisionNameAttribute(): ?string
    {
        return $this->presentDivision()?->name;
    }

    public function getPresentDistrictNameAttribute(): ?string
    {
        return $this->presentDistrict()?->name;
    }

    public function getPresentAreaNameAttribute(): ?string
    {
        return $this->presentArea()?->name;
    }

    public function getPermanentDivisionNameAttribute(): ?string
    {
        return $this->permanentDivision()?->name;
    }

    public function getPermanentDistrictNameAttribute(): ?string
    {
        return $this->permanentDistrict()?->name;
    }

    public function getPermanentAreaNameAttribute(): ?string
    {
        return $this->permanentArea()?->name;
    }
}