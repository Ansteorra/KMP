<?php

namespace GitHubIssueSubmitter\View\Cell;

use Cake\View\Cell;

class IssueSubmitterCell extends Cell
{
    protected array $_validCellOptions = ['rootView'];
    protected $rootView;
    public function display()
    {
        $this->set('rootView', $this->rootView);
    }
}