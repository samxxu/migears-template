<!DOCTYPE html>
<html>
<head>
    <title><?= $this->section('title', 'Default Title') ?></title>
</head>
<body>
    <header>
        <h1>Layout Header</h1>
    </header>
    <main>
        <?= $this->section('content') ?>
    </main>
    <footer>
        <p>Layout Footer</p>
    </footer>
</body>
</html>
