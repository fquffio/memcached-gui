<?php

define('ITEMS_PER_PAGE', 25);

function buildUrl() {
    $preservedArgs = func_get_args();
    $url = $_SERVER['PHP_SELF'];
    $args = array_intersect_key($_GET, array_flip($preservedArgs));
    return htmlspecialchars($url . '?' . (!empty($args) ? http_build_query($args) : ''), ENT_QUOTES, 'UTF-8');
}

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

$keys = $mem->getAllKeys();
if (!empty($_GET['q'])) {
    $q = $_GET['q'];
    $keys = array_filter($keys, function ($key) use ($q) {
        return (strpos($key, $q) !== false);
    });
}

$count = count($keys);
$pages = max(1, ceil($count / ITEMS_PER_PAGE));

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int) $_GET['page'] : 1;
$page = max(1, min($page, $pages));
sort($keys);
$keys = array_slice($keys, ($page - 1) * ITEMS_PER_PAGE, ITEMS_PER_PAGE);

$items = $mem->getMulti($keys);

$paginator = array();
for ($p = max(1, $page - 2); $p <= min($pages, $page + 2); $p++) {
    array_push($paginator, $p);
}

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
        <small><span class="label label-primary"><?= $count ?></span></small>
    </h1>

    <div class="row">
        <div class="col-lg-8">
            <form method="GET" action="<?= buildUrl() ?>">
                <div class="input-group">
                    <input type="search" name="q" class="form-control" placeholder="Search..." value="<?= @$_GET['q'] ?>" />
                    <span class="input-group-btn">
                        <button type="submit" class="btn btn-default" aria-label="Search">
                            <span class="glyphicon glyphicon-search" aria-hidden="true"></span>
                        </button>
                    </span>
                </div>
            </form>
        </div>
        <div class="col-lg-4">
            <button class="btn btl-lg btn-default btn-block" onclick="window.location = window.location.href">Refresh</button>
        </div>
    </div>

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
                <td>
                    <?php
                    if (is_string($value) || is_numeric($value)):
                        echo $value;
                    elseif (is_null($value) || is_bool($value)):
                        echo '<b>';
                        var_dump($value);
                        echo '</b>';
                    else:
                        echo '<i>' . (is_array($value) ? '(Array)' : '(Object)') . '</i>';
                    endif;
                    ?>
                </td>
                <td>
                    <form class="form-inline" method="POST" action="<?= buildUrl('q', 'page') ?>">
                        <input type="hidden" name="key" value="<?= $key ?>" />
                        <input type="hidden" name="_method" value="DELETE" />
                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <nav>
        <ul class="pagination">
            <li<?php if ($page == 1): ?> class="disabled"<?php endif; ?>>
                <a href="<?= buildUrl('q') ?>&amp;page=1" aria-label="First"><span aria-hidden="true">&laquo;</span></a>
            </li>
            <?php foreach ($paginator as $p): ?>
            <li<?php if ($p == $page): ?> class="active"<?php endif; ?>>
                <a href="<?= buildUrl('q') ?>&amp;page=<?= $p ?>"><?= $p ?></a>
            </li>
            <?php endforeach; ?>
            <li<?php if ($page == $pages): ?> class="disabled"<?php endif; ?>>
                <a href="<?= buildUrl('q') ?>&amp;page=<?= $pages ?>" aria-label="Last"><span aria-hidden="true">&raquo;</span></a>
            </li>
        </ul>
    </nav>

    <div class="row">
        <div class="col-md-8">
            <form class="form-inline" method="POST" action="<?= buildUrl('q', 'page') ?>">
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
            <form class="form-inline" method="POST" action="<?= buildUrl() ?>">
                <input type="hidden" name="_method" value="DELETE" />
                <button type="submit" class="btn btn-danger btn-block">Flush</button>
            </form>
        </div>
    </div>

</body>
</html>
