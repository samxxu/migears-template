<?php $this->extends('layout/main') ?>

<?php $this->start('title') ?>
    Outer title
<?php $this->end() ?>

<?php $this->start('content') ?>
    <p>Outer content</p>
<?php $this->end() ?>

<?= $this->render('nested/inner', ['value' => 'AFTER SECTIONS']) ?>
