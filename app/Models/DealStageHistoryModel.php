<?php

namespace App\Models;

use CodeIgniter\Model;

class DealStageHistoryModel extends Model
{
    protected $table         = 'deal_stage_history';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['deal_id', 'from_stage_id', 'to_stage_id', 'changed_by', 'changed_at'];
}
