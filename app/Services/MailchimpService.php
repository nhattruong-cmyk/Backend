<?php


namespace App\Services;

use DrewM\MailChimp\MailChimp;
use Illuminate\Support\Facades\Log;

class MailchimpService
{
    protected $mailchimp;

    public function __construct()
    {
        $apiKey = env('MAILCHIMP_API_KEY');
        $this->mailchimp = new MailChimp($apiKey);
    }

    public function addToList($email, $listId = null)
    {
        $listId = $listId ?? config('services.mailchimp.list_id');

        $response = $this->mailchimp->post("lists/$listId/members", [
            'email_address' => $email,
            'status' => 'unsubscribed',  // Trạng thái ban đầu là unsubscribed
        ]);

        // Kiểm tra phản hồi và ghi lại lỗi
        if (!$this->mailchimp->success()) {
            Log::error('Mailchimp error: ' . $this->mailchimp->getLastError());
            return false;
        }

        return $response;
    }
    public function updateMemberStatus($email, $status)
    {
        $listId = config('services.mailchimp.list_id');
        $subscriberHash = $this->mailchimp->subscriberHash($email);

        $response = $this->mailchimp->patch("lists/$listId/members/$subscriberHash", [
            'status' => $status,
        ]);

        if (!$this->mailchimp->success()) {
            Log::error('Mailchimp update error: ' . $this->mailchimp->getLastError());
            return false;
        }

        return $response;
    }
    public function updateSubscriptionStatus($email, $status)
    {
        $listId = config('services.mailchimp.list_id');  // ID của list trong Mailchimp

        // Tạo request API Mailchimp để cập nhật trạng thái đăng ký
        $response = $this->mailchimp->put("lists/{$listId}/members/" . md5(strtolower($email)), [
            'status' => $status  // 'unsubscribed' hoặc 'subscribed'
        ]);

        return $response;
    }

    // Hủy đăng ký người dùng (chuyển thành 'unsubscribed')
    public function unsubscribeUser($email)
    {
        return $this->updateSubscriptionStatus($email, 'unsubscribed');
    }
}
