<?php
declare(strict_types=1);

trait ResponseController {

    protected $success = true;
    protected $message = '';
    protected $status = 200;
    protected $data = null;
    protected $response = [];
    protected $errors = [];


    /**
     * @return false|string
     */
    public function apiResponse()
    {
        $this->response['success'] = $this->success;
        $this->response['message'] = $this->message;
        $this->response['status'] = $this->status;
        $this->response['data'] = $this->data;
        if(count($this->errors) > 0) {
            $this->response['errors'] = $this->errors;
        }
        return json_encode($this->response);
    }

}

