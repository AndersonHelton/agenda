<?php

namespace App\Controllers;

use App\Models\Contact;

class DashboardController extends BaseController
{
    /**
     * Listagem de recursos.
     *
     * @return string
     */
    public function index()
    {
        $contact = new Contact;
        $result = $contact->generateChart();

        $chartData = [
            'groups' => [],
            'total' => [],
            'colors' => [],
        ];
        
        foreach ($result as $item) {
            $chartData['groups'][] = $item['group'];
            $chartData['total'][] = $item['total'];
            $chartData['colors'][] = $item['color'];
        }

        return $this->view('dashboard', ['chartData' => $chartData]);
    }
}
