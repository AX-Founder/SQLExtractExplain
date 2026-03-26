<?php
/**
 * SQL Explain Plan Analyzer & Comparator (Single File)
 * DB: Oracle XE (sqler/xxxx)
 */

// 1. DB 연결
$conn = oci_connect('ID', 'PASSWORD', 'localhost:1521/XE', 'AL32UTF8');
if (!$conn) { $e = oci_error(); die("Oracle Connection Error: " . $e['message']); }

// 2. 파라미터 처리
$s_dt = $_GET['s_dt'] ?? date('Y-m-d', strtotime('-7 days'));
$e_dt = $_GET['e_dt'] ?? date('Y-m-d');
$sel_id = $_GET['id'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$rows_per_page = 10;
$offset = ($page - 1) * $rows_per_page;

// 3. 상태 업데이트 (No AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_check') {
    $t_id = $_POST['stmt_id'];
    $n_val = $_POST['curr_val'] === 'Y' ? 'N' : 'Y';
    $u_sql = "UPDATE FROM_SQL_LIST SET CHECK_YN = :v WHERE STATEMENT_ID = :id";
    $u_stid = oci_parse($conn, $u_sql);
    oci_bind_by_name($u_stid, ':v', $n_val);
    oci_bind_by_name($u_stid, ':id', $t_id);
    oci_execute($u_stid);
    header("Location: " . $_SERVER['REQUEST_URI']); exit;
}

// 4. 데이터 조회 (LT: SQL LIST)
$cnt_sql = "SELECT COUNT(*) FROM FROM_SQL_LIST WHERE REG_DT BETWEEN TO_DATE(:s,'YYYY-MM-DD') AND TO_DATE(:e,'YYYY-MM-DD')+0.99999";
$c_stid = oci_parse($conn, $cnt_sql);
oci_bind_by_name($c_stid, ':s', $s_dt); oci_bind_by_name($c_stid, ':e', $e_dt);
oci_execute($c_stid); $total_rows = oci_fetch_array($c_stid)[0];
$total_pages = ceil($total_rows / $rows_per_page);

$l_sql = "SELECT TO_CHAR(REG_DT,'YYYY-MM-DD') AS RDT, STATEMENT_ID, CHECK_YN FROM FROM_SQL_LIST 
          WHERE REG_DT BETWEEN TO_DATE(:s,'YYYY-MM-DD') AND TO_DATE(:e,'YYYY-MM-DD')+0.99999 
          ORDER BY REG_DT DESC OFFSET :off ROWS FETCH NEXT :lim ROWS ONLY";
$l_stid = oci_parse($conn, $l_sql);
oci_bind_by_name($l_stid, ':s', $s_dt); oci_bind_by_name($l_stid, ':e', $e_dt);
oci_bind_by_name($l_stid, ':off', $offset); oci_bind_by_name($l_stid, ':lim', $rows_per_page);
oci_execute($l_stid);

// 5. 상세 데이터 조회 (RT, LB, RB)
$sql_raw = ""; $f_plans = []; $t_plans = [];
if ($sel_id) {
    $q = "SELECT SQL FROM FROM_SQL_LIST WHERE STATEMENT_ID = :id";
    $s = oci_parse($conn, $q); oci_bind_by_name($s, ':id', $sel_id); oci_execute($s);
    if ($r = oci_fetch_array($s, OCI_ASSOC + OCI_RETURN_LOBS)) { $sql_raw = $r['SQL']; }

    $get_p = function($t) use ($conn, $sel_id) {
        $q = "SELECT ID, OPERATION, NAME FROM $t WHERE STATEMENT_ID = :id ORDER BY ID";
        $s = oci_parse($conn, $q); oci_bind_by_name($s, ':id', $sel_id); oci_execute($s);
        $d = []; while($r = oci_fetch_array($s, OCI_ASSOC)) { $d[$r['ID']] = $r; } return $d;
    };
    $f_plans = $get_p('FROM_SQL_EXPLAIN'); $t_plans = $get_p('TO_SQL_EXPLAIN');
}

// 헬퍼 함수
function beautify($t) {
    $kw = ['SELECT','FROM','WHERE','AND','OR','GROUP BY','ORDER BY','INSERT','UPDATE','DELETE'];
    foreach($kw as $k) { $t = str_ireplace($k, "\n$k", $t); } return htmlspecialchars(ltrim($t));
}
function fmtP($t) {
    $t = str_replace(' ', '&nbsp;', htmlspecialchars($t));
    $err = ['INDEX FULL SCAN','FULL SCAN','TABLE ACCESS FULL'];
    foreach($err as $e) { $es=str_replace(' ','&nbsp;',$e); $t=str_ireplace($es,"<span class='err'>$es</span>",$t); }
    return $t;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>SQL Plan Dashboard</title>
    <style>
        * { box-sizing: border-box; }
        body, html { margin: 0; padding: 0; height: 100%; font-family: 'Consolas', 'Monaco', monospace; background: #333; overflow: hidden; }
        .main { display: flex; flex-direction: column; height: 100vh; }
        .row { display: flex; overflow: hidden; }
        #top-row { height: 45%; }
        #bot-row { flex: 1; }
        .panel { display: flex; flex-direction: column; background: #fff; overflow: hidden; }
        .resizer-v { width: 6px; cursor: col-resize; background: #555; flex-shrink: 0; }
        .resizer-h { height: 6px; cursor: row-resize; background: #555; flex-shrink: 0; }
        .title { background: #444; color: #fff; padding: 8px 12px; font-size: 18px; font-weight: bold; }
        .content { flex: 1; overflow: auto; position: relative; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th { background: #eee; position: sticky; top: 0; z-index: 5; border-bottom: 2px solid #ccc; padding: 6px; font-size: 12px; }
        td { border: 1px solid #ddd; padding: 4px 8px; font-size: 12px; white-space: nowrap; }
        tr.active { background: #e3f2fd !important; }
        .match { background: #e8f5e9; } .mismatch { background: #fff3e0; }
        .err { color: #d32f2f; font-weight: bold; }
        .pg { padding: 5px; text-align: center; background: #f8f8f8; border-top: 1px solid #ddd; font-size: 11px; }
        .pg a, .pg b { padding: 2px 6px; margin: 0 2px; text-decoration: none; border: 1px solid #ccc; color: #333; }
        .pg b { background: #444; color: #fff; }
    </style>
</head>
<body>
<div class="main">
    <div class="row" id="top-row">
        <div class="panel" id="p-lt" style="width: 35%;">
            <div class="title">SQL LIST (<?=$total_rows?>)</div>
            <div style="padding:5px; background:#f0f0f0;">
                <form method="GET" style="font-size:11px;">
                    <input type="date" name="s_dt" value="<?=$s_dt?>"> ~ <input type="date" name="e_dt" value="<?=$e_dt?>">
                    <button type="submit">Filter</button>
                </form>
            </div>
            <div class="content">
                <table>
                    <thead><tr><th>REG_DT</th><th>STATEMENT_ID</th><th width="40">CHK</th></tr></thead>
                    <tbody>
                        <?php while($r = oci_fetch_array($l_stid, OCI_ASSOC)): ?>
                        <tr class="<?=$sel_id == $r['STATEMENT_ID']?'active':''?>">
                            <td><?=$r['RDT']?></td>
                            <td><a href="?id=<?=$r['STATEMENT_ID']?>&s_dt=<?=$s_dt?>&e_dt=<?=$e_dt?>&page=<?=$page?>"><?=$r['STATEMENT_ID']?></a></td>
                            <td align="center">
                                <form method="POST" style="margin:0;"><input type="hidden" name="action" value="toggle_check"><input type="hidden" name="stmt_id" value="<?=$r['STATEMENT_ID']?>"><input type="hidden" name="curr_val" value="<?=$r['CHECK_YN']?>"><input type="checkbox" onchange="this.form.submit()" <?=$r['CHECK_YN']=='Y'?'checked':''?>></form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <div class="pg">
                <?php for($i=1; $i<=$total_pages; $i++): ?>
                    <?php if($i==$page): ?><b><?=$i?></b><?php else: ?><a href="?s_dt=<?=$s_dt?>&e_dt=<?=$e_dt?>&id=<?=$sel_id?>&page=<?=$i?>"><?=$i?></a><?php endif; ?>
                <?php endfor; ?>
            </div>
        </div>
        <div class="resizer-v" id="rv1"></div>
        <div class="panel" style="flex:1;">
            <div class="title">SQL STATEMENT</div>
            <div class="content"><pre style="margin:10px; color:#00f;"><?=beautify($sql_raw)?></pre></div>
        </div>
    </div>
    <div class="resizer-h" id="rh"></div>
    <div class="row" id="bot-row">
        <div class="panel" id="p-lb" style="width: 50%;">
            <div class="title">FROM SQL EXPLAIN</div>
            <div class="content" id="s-lb" onmouseenter="sync('lb')">
                <table>
                    <thead><tr><th width="40">ID</th><th>OPERATION</th><th>NAME</th></tr></thead>
                    <tbody>
                        <?php foreach($f_plans as $id => $p): $m=(isset($t_plans[$id]) && $t_plans[$id]['OPERATION']==$p['OPERATION'] && $t_plans[$id]['NAME']==$p['NAME']); ?>
                        <tr class="<?=$m?'match':'mismatch'?>"><td><?=$id?></td><td><?=fmtP($p['OPERATION'])?></td><td><?=fmtP($p['NAME'])?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="resizer-v" id="rv2"></div>
        <div class="panel" style="flex:1;">
            <div class="title">TO SQL EXPLAIN</div>
            <div class="content" id="s-rb" onmouseenter="sync('rb')">
                <table>
                    <thead><tr><th width="40">ID</th><th>OPERATION</th><th>NAME</th></tr></thead>
                    <tbody>
                        <?php foreach($t_plans as $id => $p): $m=(isset($f_plans[$id]) && $f_plans[$id]['OPERATION']==$p['OPERATION'] && $f_plans[$id]['NAME']==$p['NAME']); ?>
                        <tr class="<?=$m?'match':'mismatch'?>"><td><?=$id?></td><td><?=fmtP($p['OPERATION'])?></td><td><?=fmtP($p['NAME'])?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
// Resizing 로직
const setupR = (h, m) => {
    h.onmousedown = (e) => {
        document.onmousemove = (me) => {
            if (m === 'V') {
                const pct = (me.clientX / window.innerWidth) * 100;
                document.getElementById('p-lt').style.width = pct + '%';
                document.getElementById('p-lb').style.width = pct + '%';
            } else {
                const pct = (me.clientY / window.innerHeight) * 100;
                document.getElementById('top-row').style.height = pct + '%';
            }
        };
        document.onmouseup = () => document.onmousemove = null;
    };
};
setupR(document.getElementById('rv1'), 'V');
setupR(document.getElementById('rv2'), 'V');
setupR(document.getElementById('rh'), 'H');

// 스크롤 동기화
const lb = document.getElementById('s-lb'), rb = document.getElementById('s-rb');
function sync(type) {
    if (type === 'lb') {
        lb.onscroll = () => { rb.scrollTop = lb.scrollTop; rb.scrollLeft = lb.scrollLeft; };
        rb.onscroll = null;
    } else {
        rb.onscroll = () => { lb.scrollTop = rb.scrollTop; lb.scrollLeft = rb.scrollLeft; };
        lb.onscroll = null;
    }
}
</script>
</body>
</html>
