<?php $this->extends('layout/main') ?>

<?php $this->start('title') ?>
    Hello, <?= $this->e($name) ?>
<?php $this->end() ?>

<?php $this->start('content') ?>
    <h2>Profile of <?= $this->e($name) ?></h2>
    <p>Age: <?= $age ?></p>
    <?= $this->raw($bio) ?>
<?php $this->end() ?>
