<?php $this->extends('layout/main') ?>

<?php $this->start('content') ?>A[<?= $this->render('nested/inner', ['value' => 'INSIDE']) ?>]B<?php $this->end() ?>
