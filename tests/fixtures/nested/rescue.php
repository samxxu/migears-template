<?php $this->extends('layout/main') ?>

<?php $this->start('content') ?>
    <?php try { ?>
        <?= $this->render('nested/boom') ?>
    <?php } catch (\RuntimeException $e) { ?>
        caught: <?= $this->e($e->getMessage()) ?>
    <?php } ?>
<?php $this->end() ?>
