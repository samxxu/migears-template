<h1>Hello, <?= $this->e($name) ?>!</h1>
<ul>
<?php foreach ($items as $item): ?>
    <li><?= $this->e($item) ?></li>
<?php endforeach ?>
</ul>
