<?php

if (!function_exists('shop_config')) { return; }

$__cfg  = shop_config(true);
$__c    = $__cfg['contact'] ?? [];
$__pay  = $__cfg['pay'] ?? [];

$g = function ($k, $d = '') use ($__c) { return (string)($__c[$k] ?? $d); };

?>
<style>
.ct-field{margin-bottom:15px;}
.ct-field label{display:block;font-size:13.5px;font-weight:600;margin-bottom:8px;color:var(--text);}
.ct-field .hint{font-weight:400;font-size:12px;color:var(--text3);}
.ct-field input[type=text],.ct-field input[type=email],.ct-field textarea{
  width:100%;padding:10px 13px;border-radius:9px;border:1px solid var(--border);
  background:var(--surface2);color:var(--text);font-size:13.5px;outline:none;font-family:inherit;
  transition:.15s;box-sizing:border-box;
}
.ct-field input:focus,.ct-field textarea:focus{border-color:var(--primary);background:var(--surface);}
.ct-field textarea{min-height:88px;resize:vertical;line-height:1.7;}
.ct-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:0 18px;}
.ct-check{display:flex;align-items:center;gap:8px;font-size:13.5px;font-weight:600;cursor:pointer;}
.ct-check input{width:16px;height:16px;accent-color:var(--primary);cursor:pointer;}
.ct-qr-preview{
  display:block;width:auto;height:auto;max-width:min(170px,42vw);max-height:170px;
  border-radius:12px;border:1px solid var(--border);cursor:zoom-in;transition:transform .2s,box-shadow .2s;
}
.ct-qr-preview:hover{transform:scale(1.03);box-shadow:0 10px 26px rgba(0,0,0,.18);}
.ct-qr-hint{max-width:min(170px,42vw);margin-top:8px;font-size:12px;color:var(--text3);text-align:center;}
.ct-qr-empty{
  max-width:min(170px,42vw);height:150px;border-radius:12px;border:2px dashed var(--border-strong);
  display:flex;align-items:center;justify-content:center;color:var(--text3);
  font-size:12.5px;text-align:center;padding:14px;line-height:1.7;
}
.ct-h3{font-size:14.5px;font-weight:700;margin:26px 0 12px;padding-left:10px;border-left:3px solid var(--primary);}

.qr-zoom{
  position:fixed;inset:0;z-index:10008;display:none;flex-direction:column;align-items:center;
  justify-content:center;gap:12px;padding:20px;overflow:hidden;
  background:rgba(8,14,26,.9);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);
}
.qr-zoom.show{display:flex;}
.qr-zoom img{flex-shrink:0;width:auto;height:auto;max-width:min(88vw,68vh);max-height:min(88vw,68vh);
  border-radius:14px;box-shadow:0 24px 70px rgba(0,0,0,.5);}
.qr-zoom .qz-close{position:absolute;top:16px;right:16px;width:42px;height:42px;border-radius:50%;
  border:none;background:rgba(255,255,255,.16);color:#fff;font-size:24px;line-height:1;cursor:pointer;transition:.2s;}
.qr-zoom .qz-close:hover{background:rgba(255,255,255,.3);}
.qr-zoom .qz-hint{max-width:430px;color:rgba(255,255,255,.62);font-size:12.5px;line-height:1.7;text-align:center;}
</style>
<?php
$field = function ($name, $label, $val, $ph = '', $hint = '', $type = 'text') use ($g) {
    echo '<div class="ct-field">';
    echo '<label>' . htmlspecialchars($label);
    if ($hint !== '') echo ' <span class="hint">（' . $hint . '）</span>';
    echo '</label>';
    echo '<input type="' . $type . '" name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars($val) . '"'
       . ($ph !== '' ? ' placeholder="' . htmlspecialchars($ph) . '"' : '') . '>';
    echo '</div>';
};
?>
<div class="card">
  <h2>联系方式页</h2>
  <div class="tips">
    这些内容会显示在客户端的<strong>「联系方式」页面</strong>
    （<code>contact.php</code>，地址形如 <code>你的域名/contact.php</code>）。<br>
    页面里有：联系方式（可一键复制）、微信二维码（可点击放大）、抖音主页与直播间入口。
    留空的项<strong>会自动隐藏</strong>，不会出现空白区块。
  </div>

  <form method="post" class="edit-panel">
    <input type="hidden" name="action" value="save_contact">
    <?php echo csrf_field(); ?>

    <h3 class="ct-h3" style="margin-top:22px;">① 基本设置</h3>
    <div class="ct-field">
      <label class="ct-check">
        <input type="checkbox" name="enable" value="1" <?php echo !isset($__c['enable']) || $__c['enable'] ? 'checked' : ''; ?>>
        启用「联系方式」页面
      </label>
      <div class="hint" style="margin-top:6px;">取消勾选后，客户访问 contact.php 会自动跳回商城首页。</div>
    </div>

    <div class="ct-grid">
      <?php
        $field('owner_name', '称呼 / 昵称', $g('owner_name', '站长'), '站长', '用于「联系站长」这类默认文案');
        $field('title', '页面主标题', $g('title'), '留空则自动用「联系 + 称呼」');
      ?>
    </div>
    <?php
      $field('subtitle', '页面副标题', $g('subtitle'), '有疑问随时找我，看到会尽快回复');
      $field('notice', '顶部提示条', $g('notice'), '留空则不显示', '支持多行');
    ?>

    <h3 class="ct-h3" style="margin-top:26px;">② 联系方式</h3>
    <div class="tips" style="margin-bottom:12px;">每一项留空就不显示；客户可以一键复制。</div>
    <div class="ct-grid">
      <?php
        $field('wechat', '💬 微信号', $g('wechat'), 'your_wechat');
        $field('qq',     '🐧 QQ 号',  $g('qq'),     '123456789');
        $field('phone',  '📱 电话',   $g('phone'),  '13800138000');
        $field('email',  '✉️ 邮箱',   $g('email'),  'me@example.com');
      ?>
    </div>
    <?php $field('work_hours', '🕒 服务时间', $g('work_hours'), '每天 9:00 - 22:00'); ?>

    <h3 class="ct-h3" style="margin-top:26px;">③ 微信二维码</h3>
    <div style="display:flex;gap:20px;flex-wrap:wrap;align-items:flex-start;">
      <div style="flex:0 0 auto;">
        <label style="display:block;font-size:13.5px;font-weight:600;margin-bottom:9px;">当前预览</label>
        <?php if (trim($g('qr_url')) !== ''): ?>
          <img class="ct-qr-preview" src="<?php echo htmlspecialchars($g('qr_url')); ?>" alt="微信二维码"
               referrerpolicy="no-referrer"
               onclick="document.getElementById('ctQrZoomImg').src=this.src;document.getElementById('ctQrZoom').classList.add('show');">
          <div class="ct-qr-hint">点击可放大查看</div>
        <?php else: ?>
          <div class="ct-qr-empty">尚未配置二维码<br>请在右侧填写图片地址</div>
        <?php endif; ?>
      </div>
      <div style="flex:1;min-width:260px;">
        <?php
          $field('qr_url', '二维码图片地址', $g('qr_url'), 'https://.../wechat.png', '建议用外链图床，与商城其他图片一致');
          $field('qr_tip', '二维码下方说明', $g('qr_tip', '扫码添加微信'), '扫码添加微信');
        ?>
        <div class="tips">
          二维码按<strong>图片自身比例</strong>显示，不会出现白边；
          客户点击可放大到满屏，方便长按识别。
        </div>
      </div>
    </div>

    <h3 class="ct-h3" style="margin-top:26px;">④ 抖音</h3>
    <div class="tips" style="margin-bottom:12px;">
      在抖音 App 里点「分享 → 复制链接」拿到地址粘进来即可。留空则对应按钮不显示。
    </div>
    <?php
      $field('douyin_home', '🎵 抖音主页链接', $g('douyin_home'), 'https://www.douyin.com/user/xxxxx');
      $field('douyin_live', '📺 抖音直播间链接', $g('douyin_live'), 'https://live.douyin.com/xxxxx', '开播时把直播间链接粘进来，客户可直接点进');
      $field('douyin_id',   '抖音号（展示用）', $g('douyin_id'), 'your_douyin', '只是展示，不是链接');
    ?>

    <h3 class="ct-h3" style="margin-top:26px;">⑤ 补充说明</h3>
    <?php $field('extra', '补充说明', $g('extra'), "每行一条，例如：\n加微信请备注「下单」\n急事请直接打电话", '一行一条，会自动渲染成列表'); ?>
    <?php $field('footer_note', '页面底部说明', $g('footer_note'), '留空则沿用「商城下单 → 页面文案」里的页脚说明'); ?>

    <div style="margin-top:24px;display:flex;gap:10px;flex-wrap:wrap;">
      <button type="submit" class="btn btn-primary">保存设置</button>
      <a class="btn btn-secondary" href="contact.php" target="_blank">👁 预览联系方式页</a>
    </div>
  </form>
</div>

<div class="qr-zoom" id="ctQrZoom" onclick="if(event.target===this)this.classList.remove('show')">
  <button class="qz-close" onclick="document.getElementById('ctQrZoom').classList.remove('show')" aria-label="关闭">×</button>
  <img id="ctQrZoomImg" src="" alt="微信二维码" referrerpolicy="no-referrer">
  <div class="qz-hint">这是客户在「联系方式」页看到的二维码；满屏查看可确认图片清晰、无裁切。点击空白处或按 Esc 关闭</div>
</div>
<script>
document.addEventListener('keydown', function(e){
  if (e.key === 'Escape') { var z = document.getElementById('ctQrZoom'); if (z) z.classList.remove('show'); }
});
</script>
