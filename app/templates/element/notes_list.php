<?php
declare(strict_types=1);

foreach ($notes as $note) {
    echo $this->element('note', compact('note'));
}
