<?php $this->extends('layout/main') ?>

<?php $this->section('title') ?>
    Hello, <?= $this->e($name) ?>
<?php $this->endSection() ?>

<?php $this->section('content') ?>
    <h2>Profile of <?= $this->e($name) ?></h2>
    <p>Age: <?= $age ?></p>
    <?= $this->raw($bio) ?>
<?php $this->endSection() ?>
