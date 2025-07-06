<?php

namespace App\Models;

use CodeIgniter\Model;

class LeaveModel extends Model
{
    protected $table = 'emp_leave';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = [
        'userid', 'leave_type', 'leave_details', 'leave_reason', 'date_from', 'date_to',
        'appdate', 'leave_status', 'supervisor', 'manager', 'hr', 'notes'
    ];

    // Dates
    protected $useTimestamps = false;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';

    // Validation
    protected $validationRules = [
        'userid' => 'required|integer',
        'leave_type' => 'required|max_length[100]',
        'leave_reason' => 'required|max_length[500]',
        'date_from' => 'required|valid_date',
        'date_to' => 'required|valid_date',
    ];
    protected $validationMessages = [];
    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert = [];
    protected $afterInsert = [];
    protected $beforeUpdate = [];
    protected $afterUpdate = [];
    protected $beforeFind = [];
    protected $afterFind = [];
    protected $beforeDelete = [];
    protected $afterDelete = [];

    /**
     * Get leave applications by user ID
     */
    public function getLeavesByUserId(int $userId, int $limit = 10, int $offset = 0)
    {
        return $this->where('userid', $userId)
            ->orderBy('appdate', 'DESC')
            ->findAll($limit, $offset);
    }

    /**
     * Get pending leave applications for approval
     */
    public function getPendingLeaves(int $approverUserId, int $limit = 10, int $offset = 0)
    {
        return $this->db->table('emp_leave l')
            ->select('l.*, e.fullname')
            ->join('employee e', 'l.userid = e.id')
            ->where('(l.hr = ' . $approverUserId . ' AND l.leave_status = 1)')
            ->orWhere('(l.supervisor = ' . $approverUserId . ' AND l.leave_status = 2)')
            ->orWhere('(l.manager = ' . $approverUserId . ' AND l.leave_status = 3)')
            ->orderBy('l.appdate', 'DESC')
            ->limit($limit, $offset)
            ->get()
            ->getResultArray();
    }

    /**
     * Get leave details by ID
     */
    public function getLeaveDetails(int $leaveId)
    {
        return $this->db->table('emp_leave l')
            ->select('l.*, e.fullname')
            ->join('employee e', 'l.userid = e.id')
            ->where('l.id', $leaveId)
            ->get()
            ->getRowArray();
    }

    /**
     * Update leave status
     */
    public function updateLeaveStatus(int $leaveId, int $status, string $notes = '')
    {
        $data = ['leave_status' => $status];
        if (!empty($notes)) {
            $data['notes'] = $notes;
        }
        return $this->update($leaveId, $data);
    }
}