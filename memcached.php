<?php

$config = 'config.ini';
$mem = new Memcached();

if (!file_exists($config) || !is_readable($config) || !($config = parse_ini_file($config, true))) {
    trigger_error('Missing configuration file.', E_USER_ERROR);
}
$mem->addServers(array_values($config));

$_method = isset($_POST['_method']) ? $_POST['_method'] : $_SERVER['REQUEST_METHOD'];
switch ($_method) {
    case 'DELETE':
        if (isset($_POST['key'])) {
            $mem->delete($_POST['key']);
        } else {
            $mem->flush();
        }
        break;
    case 'POST':
        if (!empty($_POST['key'])) {
            $mem->set($_POST['key'], @$_POST['value']);
        }
        break;
}

$items = $mem->getMulti($mem->getAllKeys());
ksort($items);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Basic Memcached PHP interface</title>

    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css">
</head>

<body>
<div class="container" style="width: 940px;">
    <h1>
        Basic Memcached PHP interface
        <button class="btn btl-lg btn-default" onclick="window.location = window.location.href">Refresh</button>
    </h1>

    <table class="table table-bordered table-hover table-striped">
        <thead>
            <tr>
                <th>Key</th>
                <th>Value</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $key => $value): ?>
            <tr<?php if ($_method == 'POST' && @$_POST['key'] == $key): ?> class="success"<?php endif; ?>>
                <td><?= $key ?></td>
                <td><?= $value ?></td>
                <td>
                    <form class="form-inline" method="POST" action="">
                        <input type="hidden" name="key" value="<?= $key ?>" />
                        <input type="hidden" name="_method" value="DELETE" />
                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="row">
        <div class="col-md-8">
            <form class="form-inline" method="POST" action="">
                <div class="form-group">
                    <label for="key">Key</label>
                    <input type="text" class="form-control" id="key" name="key" placeholder="key" required />
                </div>
                <div class="form-group">
                    <label for="value">Value</label>
                    <input type="text" class="form-control" id="value" name="value" placeholder="value" required />
                </div>
                <button type="submit" class="btn btn-primary">Set key</button>
            </form>
        </div>
        <div class="col-md-4">
            <form class="form-inline" method="POST" action="">
                <input type="hidden" name="_method" value="DELETE" />
                <button type="submit" class="btn btn-danger btn-block">Flush</button>
            </form>
        </div>
    </div>

</body>
</html>
