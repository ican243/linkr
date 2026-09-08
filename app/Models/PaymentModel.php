<?php
  
  namespace App\Models;
  
  use CodeIgniter\Model;
  
  class PaymentModel extends Model
  {
      protected $table            = 'payments';
      protected $primaryKey       = 'id';
      protected $useAutoIncrement = true;
      protected $returnType       = 'array';
      protected $allowedFields    = [
          'user_id', 'order_id', 'amount', 'plan', 'status', 'payment_key', 'method', 'receipt_url',
          'admin_id', 'admin_memo', 'refunded_at', 'cancel_reason', 'toss_response', 'approved_at',
      ];
      
      protected $useTimestamps = true;
      protected $dateFormat    = 'datetime';
      protected $createdField  = 'created_at';
      protected $updatedField  = ''; // updated_at 컬럼이 없으므로 비워둠
  }   
