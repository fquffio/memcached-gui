<?php

/**
 * Items to show on each page.
 */
define('ITEMS_PER_PAGE', 25);

/**
 * Build a URL. You can pass multiple parameters to this function to preserve GET keys/values.
 *
 * @return string
 */
function buildUrl() {
    $preservedArgs = func_get_args();
    $url = $_SERVER['PHP_SELF'];
    $args = array_intersect_key($_GET, array_flip($preservedArgs));
    return htmlspecialchars($url . '?' . (!empty($args) ? http_build_query($args) : ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Transforms a dimension in bytes to a human readable string.
 *
 * @param int $size
 * @return string
 */
function toHumanReadable($size) {
    static $sizes = 'BKMGTP';
    $factor = floor((strlen($size) - 1) / 3);
    return sprintf('%.2f', $size / pow(1024, $factor)) . @$sizes[$factor];
}

/**
 * Returns an array in a printable table.
 *
 * @param array $data
 * @return string
 */
function toTextTable(array $data) {
    $maxLen = function($carry, $item) {
        return max($carry, strlen($item));
    };
    $columns = array();
    $maxLenghts = array();
    foreach ($data as $id => $row) {
        $columns = array_merge($columns, array_keys($row));
        $maxLenghts[$id] = array_reduce($row, $maxLen, strlen($id));
    }
    $columns = array_unique($columns);
    $maxCol = array_reduce($columns, $maxLen, 0);

    $separator = '+' . str_repeat('-', $maxCol + 2) . '+';
    $header = '|' . str_repeat(' ', $maxCol + 2) . '|';
    foreach (array_keys($data) as $id) {
        $separator .= str_repeat('-', $maxLenghts[$id] + 2) . '+';
        $header .= ' ' . str_pad($id, $maxLenghts[$id], ' ', STR_PAD_BOTH) . ' |';
    }

    $res = $separator . PHP_EOL . $header . PHP_EOL . $separator . PHP_EOL;
    foreach ($columns as $col) {
        $res .= '| ' . str_pad(strtoupper($col), $maxCol, ' ', STR_PAD_LEFT) . ' |';
        foreach ($data as $id => $row) {
            $res .= ' ' . str_pad(@$row[$col], $maxLenghts[$id]) . ' |';
        }
        $res .= PHP_EOL;
    }
    return $res . $separator;
}

$config = 'config.ini';
$mem = new Memcached();

if (!file_exists($config) || !is_readable($config) || !($config = parse_ini_file($config, true))) {
    trigger_error('Missing configuration file.', E_USER_ERROR);
}
$mem->addServers(array_values($config));

$_method = isset($_POST['_method']) ? $_POST['_method'] : $_SERVER['REQUEST_METHOD'];
switch ($_method) {
    case 'GET':
        if (isset($_GET['stats'])) {
            header('Content-Type: text/plain');
            echo toTextTable($mem->getStats());
            exit;
        }
        if (!empty($_GET['key'])) {
            header('Content-Type: text/plain');
            $value = $mem->get($_GET['key']);
            if ($mem->getResultCode() == Memcached::RES_NOTFOUND) {
                header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
                echo 'NOT FOUND!';
            } elseif (is_string($value) || is_numeric($value)) {
                echo $value;
            } elseif (is_null($value) || is_bool($value)) {
                var_dump($value);
            } else {
                print_r($value);
            }
            exit;
        }
        break;
    case 'DELETE':
        if (isset($_POST['key'])) {
            $mem->delete($_POST['key']);
        } else {
            $mem->flush();
            $mem->getMulti($mem->getAllKeys());
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
$diff = 2 + max(0, 3 - $page, $page - $pages + 2);
for ($p = max(1, $page - $diff); $p <= min($pages, $page + $diff); $p++) {
    array_push($paginator, $p);
}

$stats = $mem->getStats();
$overview = array();
array_walk_recursive($stats, function($item, $key) use (&$overview) {
    $overview[$key] = isset($overview[$key]) ?  $item + $overview[$key] : $item;
});

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Basic Memcached PHP interface</title>

    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css">
    <script type="text/javascript">
        var openPopup = function(url) {
                var w = 630,
                    h = 440,
                    percent = .33;
                if (window.screen) {
                    w = window.screen.availWidth * percent;
                    h = window.screen.availHeight * percent;
                }
                window.open(url, '_blank', 'width=' + w + ', height=' + h);
            },
            deletionConfirm = function(evt) {
                if (!window.confirm('Are you sure you wish to proceed?')) {
                    evt.preventDefault();
                }
            },
            pageRefresh = function() {
                window.location = window.location.href;
            };
    </script>
</head>

<body>
<div class="container" style="width: 940px;">
    <h1>Basic Memcached PHP interface</h1>

    <div class="panel panel-default">
        <div class="panel-heading">
            <h2 class="panel-title">Stored keys <span class="label label-primary"><?= $count ?></span></h2>
        </div>

        <div class="panel-body">
            <div class="row">
                <div class="col-lg-8">
                    <form method="GET" action="<?= buildUrl() ?>">
                        <div class="input-group">
                            <input type="search" name="q" class="form-control" placeholder="Search&hellip;" value="<?= @$_GET['q'] ?>" />
                            <span class="input-group-btn">
                                <button type="submit" class="btn btn-default" aria-label="Search">
                                    <span class="glyphicon glyphicon-search" aria-hidden="true"></span>
                                </button>
                            </span>
                        </div>
                    </form>
                </div>
                <div class="col-md-4">
                    <form class="form-inline" method="POST" action="<?= buildUrl() ?>">
                        <input type="hidden" name="_method" value="DELETE" />
                        <div class="btn-group btn-group-justified" role="group">
                            <div class="btn-group" role="group"><button type="button" class="btn btl-lg btn-default" onclick="pageRefresh()">Refresh</button></div>
                            <div class="btn-group" role="group"><button type="submit" class="btn btn-danger">Flush</button></div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <table class="table table-bordered table-hover table-striped">
            <thead>
                <tr>
                    <th class="col-md-5">Key</th>
                    <th class="col-md-5">Value</th>
                    <th class="col-md-2">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $key => $value): ?>
                <tr<?php if ($_method == 'POST' && @$_POST['key'] == $key): ?> class="success"<?php endif; ?>>
                    <td><?= $key ?></td>
                    <td>
                        <?php
                        if (is_string($value) || is_numeric($value)):
                            echo (strlen((string) $value) >= 512) ? substr($value, 0, 512) . '&hellip;' : $value;
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
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-default btn-sm" onclick="openPopup('<?= buildUrl() ?>key=<?= $key ?>')">View Raw</button>
                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                            </div>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div class="panel-footer">

            <div class="row">
                <div class="col-md-4">
                    <?php if ($pages > 1): ?>
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
                    <?php endif; ?>
                </div>
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
                        <button type="submit" class="btn btn-primary">Store</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="panel panel-primary">
        <div class="panel-heading">
            <h2 class="panel-title">Server statistics</h2>
        </div>
        <table class="table table-bordered table-hover table-striped">
            <thead>
                <tr>
                    <th class="col-md-4">Server</th>
                    <th class="col-md-8">Usage</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stats as $server => $data): ?>
                <?php $data['bytes_ratio'] = $data['bytes'] / $data['limit_maxbytes']; $perc = sprintf('%01.1f%%', 100 * $data['bytes_ratio']); ?>
                <tr>
                    <td><?= $server ?></td>
                    <td>
                        <div class="progress">
                            <div
                                class="progress-bar progress-bar-<?= ($data['bytes_ratio'] > .9) ? 'danger' : ($data['bytes_ratio'] > .75 ? 'warning' : 'success') ?>"
                                role="progressbar" aria-valuenow="<?= $data['bytes'] ?>" aria-valuemin="0" aria-valuemax="<?= $data['limit_maxbytes'] ?>" style="min-width: 2em; width: <?= $perc ?>"
                                data-toggle="tooltip" data-placement="top" title="<?= toHumanReadable($data['bytes']) . ' / ' . toHumanReadable($data['limit_maxbytes']) ?>"
                            >
                                <?= $perc ?>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <?php if (count($stats) > 1): ?>
            <tfoot>
                <?php $overview['bytes_ratio'] = $overview['bytes'] / $overview['limit_maxbytes']; $perc = sprintf('%01.1f%%', 100 * $overview['bytes_ratio']); ?>
                <tr>
                    <td>Overall</td>
                    <td>
                        <div class="progress">
                            <div
                                class="progress-bar progress-bar-<?= ($overview['bytes_ratio'] > .9) ? 'danger' : ($overview['bytes_ratio'] > .75 ? 'warning' : 'success') ?>"
                                role="progressbar" aria-valuenow="<?= $overview['bytes'] ?>" aria-valuemin="0" aria-valuemax="<?= $overview['limit_maxbytes'] ?>" style="min-width: 2em; width: <?= $perc ?>"
                                data-toggle="tooltip" data-placement="top" title="<?= toHumanReadable($overview['bytes']) . ' / ' . toHumanReadable($overview['limit_maxbytes']) ?>"
                            >
                                <?= $perc ?>
                            </div>
                        </div>
                    </td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
        <div class="panel-footer">
            <button type="button" class="btn btn-default btn-block btn-primary" onclick="openPopup('<?= buildUrl() ?>stats')">View Detailed Stats</button>
        </div>
    </div>

    <script type="text/javascript" src="https://code.jquery.com/jquery-2.1.4.min.js"></script>
    <script type="text/javascript" src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/js/bootstrap.min.js"></script>
    <script type="text/javascript">
        (function() {
            var elements = document.querySelectorAll('.btn-danger');
            for (var i = 0; i < elements.length; i++) {
                elements[i].addEventListener('click', deletionConfirm);
            }

            $('[data-toggle="tooltip"]').tooltip();
        })();
    </script>
</body>
</html>
