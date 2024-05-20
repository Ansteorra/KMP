<?php
declare(strict_types=1);

namespace App\View\Helper;

use Cake\View\Helper;
use Cake\View\StringTemplateTrait;
use Cake\Log\Log;
/**
 * helper for KMP specific UI elements
 *
 * 
 *
 * 
 */
class KmpHelper extends Helper
{
    public function bool($value, $Html): string{
        return $value?$Html->icon('check-circle-fill'):$Html->icon('x-circle');
    }

    public function appControllerNav($label, $urlparams, $request, $Html, $user, $icon, $sublinks = []): string{
        //is $urlparams a string or an array?
        $url = [];
        if(is_string($urlparams)){
            $controller = $urlparams;
            $url = ['controller' => $controller, 'action' => 'index'];
        }else{
            $controller = $urlparams['controller'];
            $url = $urlparams;
        }
        if($user->can('index', $controller)) {
            $urlDetails = 
            $return = '';
            $activeclass = $request->getParam('controller') === $controller ? 'active' : '';
            $return .= $Html->link(__(' '. $label), $url, ['class' => 'nav-link fs-5 bi '.$icon.' pb-0 '.$activeclass]);
            foreach($sublinks as $sublink){
                $suburl = $sublink['suburl'];
                if($request->getParam('controller') === $controller && $user->can($suburl['action'], $suburl['controller'])) {
                    $return .= $Html->link(__(' '.$sublink['label']), $suburl, ['class' => 'nav-link bi '.$sublink['icon'].' ms-3 fs-6 pt-0']);
                }
            }
            return $return;
        }
        return '';
    }

    public function appControllerNavSpacer($label, $Html, $icon, ): string{
        
        $return = '<div class="badge fs-5 text-bg-secondary bi '.$icon.'"> '.$label.'</div>';
        return $return;
    }
}