<?php

namespace Drupal\commerce_paymill\Models\Request;

use Paymill\Models\Request\Checksum as Base;

class Checksum extends Base
{
    /**
     * @var string
     */
    private $clientEmail;
    /**
     * @return string
     */
    public function getClientEmail()
    {
        return $this->clientEmail;
    }

    /**
     * @param string $clientEmail
     * @return Checksum
     */
    public function setClientEmail($clientEmail)
    {
        $this->clientEmail = $clientEmail;
        return $this;
    }


    public function parameterize($method)
    {
        $ret = parent::parameterize($method);

        if (
            'create'==$method
            && $this->getClientEmail()
        ) {
            $ret['customer_email'] = $this->getClientEmail();
        }

        return $ret;
    }
}