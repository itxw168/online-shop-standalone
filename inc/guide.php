<?php

if (!function_exists('shop_config')) { return; }
?>
<style>
.gd-wrap{max-width:960px;}
.gd-lead{
  background:var(--primary-soft);border:1px solid var(--primary);border-radius:12px;
  padding:14px 18px;font-size:13.5px;line-height:1.9;color:var(--text);margin-bottom:20px;
}
.gd-sec{
  background:var(--surface);border:1px solid var(--border);border-radius:12px;
  margin-bottom:12px;overflow:hidden;
}
.gd-sec > summary{
  cursor:pointer;list-style:none;padding:14px 18px;font-size:14.5px;font-weight:700;
  display:flex;align-items:center;gap:9px;color:var(--text);user-select:none;transition:.15s;
}
.gd-sec > summary::-webkit-details-marker{display:none;}
.gd-sec > summary:hover{background:var(--surface2);}
.gd-sec > summary::after{content:'▾';margin-left:auto;font-size:12px;opacity:.5;transition:transform .2s;}
.gd-sec[open] > summary::after{transform:rotate(180deg);}
.gd-sec[open] > summary{border-bottom:1px solid var(--border);}
.gd-body{padding:14px 20px 18px;font-size:13.5px;line-height:1.95;color:var(--text2);}
.gd-body ul{margin:0;padding-left:20px;}
.gd-body li{margin-bottom:7px;}
.gd-body b,.gd-body strong{color:var(--text);}
.gd-body code{
  background:var(--surface2);border:1px solid var(--border);border-radius:5px;
  padding:1px 6px;font-size:12.5px;font-family:Consolas,Monaco,monospace;color:var(--text);
}
.gd-body .gd-note{
  margin-top:10px;padding:10px 14px;border-radius:9px;font-size:13px;
  background:var(--warn-soft);color:var(--warn);border:1px solid var(--warn);
}
.gd-body .gd-tip{
  margin-top:10px;padding:10px 14px;border-radius:9px;font-size:13px;
  background:var(--info-soft);color:var(--info-text);border:1px solid var(--info-border);
}
.gd-table{width:100%;border-collapse:collapse;margin-top:6px;font-size:13px;}
.gd-table th,.gd-table td{padding:8px 11px;border-bottom:1px solid var(--border);text-align:left;vertical-align:top;}
.gd-table th{color:var(--text);font-weight:600;background:var(--surface2);white-space:nowrap;}
.gd-table tr:last-child td{border-bottom:none;}
.gd-steps{counter-reset:st;list-style:none;padding:0;}
.gd-steps li{
  counter-increment:st;position:relative;padding-left:34px;margin-bottom:11px;
}
.gd-steps li::before{
  content:counter(st);position:absolute;left:0;top:1px;width:22px;height:22px;border-radius:50%;
  background:var(--primary);color:#fff;font-size:12px;font-weight:700;
  display:flex;align-items:center;justify-content:center;
}
</style>

<div class="gd-wrap">
  <div class="card">
    <h2>使用说明</h2>
    <div class="gd-lead">
      这是<strong>在线商城独立站</strong>的后台。整站只有商城一个模块，所有配置都在左边栏里，
      数据全部存在本站的 <code>data/</code> 目录下（配置加密、订单用 SQLite），<strong>不依赖任何其它站点</strong>。<br>
      第一次用建议按下面的「三步跑起来」走一遍。
    </div>

    <details class="gd-sec" open>
      <summary>🚀 三步跑起来</summary>
      <div class="gd-body">
        <ol class="gd-steps">
          <li><strong>填收款码</strong> —— 左侧栏「💳 收款与表单」，把微信/支付宝收款码图片地址粘进去。
              <div class="gd-tip">没填收款码，客户下单后会看到「管理员尚未配置收款码」，无法付款。</div></li>
          <li><strong>检查商品</strong> —— 左侧栏「📦 商品管理」，确认价格、分类、图标都对。
              分类在「🗂 分类管理」里增删。</li>
          <li><strong>配一条推送</strong> —— 左侧栏「🔔 消息推送」，接一个通道（Gotify / ntfy / 邮件 / Webhook），
              点「测试推送」确认手机能收到。
              <div class="gd-note">不配推送，有人下单你不会收到任何提醒，只能自己盯着后台。</div></li>
        </ol>
      </div>
    </details>

    <details class="gd-sec">
      <summary>📋 每个功能页是干什么的</summary>
      <div class="gd-body">
        <table class="gd-table">
          <tr><th>页面</th><th>作用</th></tr>
          <tr><td>📋 订单管理</td><td>看订单、改状态、导出 CSV。可按状态筛选、按订单号/联系人/微信/电话/备注码/商品名搜索。<strong>「支付方式」列</strong>显示客户选的微信/支付宝（聚合码模式显示「聚合码」）。<strong>点每行「详情」</strong>能看到客户填的全部信息（含下单 IP 与归属地）。</td></tr>
          <tr><td>📦 商品管理</td><td>新增/编辑商品：名称、分类、价格、计价单位、库存、标签、图片、图标 emoji、说明。可上下调整顺序。</td></tr>
          <tr><td>🗂 分类管理</td><td>给商品分组，会显示在客户端首页的卡片里。可增删改、调顺序。</td></tr>
          <tr><td>✏️ 页面文案</td><td>商城首页的标题、副标题、公告，以及顶栏/页脚的按钮文案与链接。</td></tr>
          <tr><td>💳 收款与表单</td><td><strong>收款方式（聚合码 / 微信支付宝分开）</strong>、收款码、收款人名称、备注码规则、<strong>未付款订单自动失效时间</strong>、已失效订单保留天数、联系管理员链接、付款页提示语、下单表单必填项。</td></tr>
          <tr><td>🔔 消息推送</td><td>新订单推送到手机。支持 Gotify / ntfy / 邮件 / 通用 Webhook，可同时开多个互为备份；可自定义通知标题与正文模板；可逐个通道测试、手动处理重试队列、<strong>查看与清理推送记录</strong>。</td></tr>
          <tr><td>🛡 风控与清理</td><td>解除被限流的 IP、清理未付款/已失效订单、查看当前风控规则。正常情况都会自动过期，这里用于「误封了真实客户」或「想立刻清掉垃圾单」。</td></tr>
          <tr><td>🧾 操作日志</td><td>后台每一次写操作的记录：谁、何时、从哪个 IP、做了什么、结果如何。可筛选、搜索、清理。</td></tr>
          <tr><td>📇 联系方式页</td><td>编辑客户端「联系方式」页（<code>contact.php</code>）：联系方式、微信二维码、抖音主页与直播间。</td></tr>
          <tr><td>🔑 账号设置</td><td>修改后台<strong>登录账号名和密码</strong>（任一项改动都要先验证原密码）。</td></tr>
        </table>
      </div>
    </details>

    <details class="gd-sec">
      <summary>💰 收款方式怎么配（聚合码 / 微信支付宝分开）</summary>
      <div class="gd-body">
        <p style="margin:0 0 10px;">「收款与表单 → 收款码设置」最上方有<strong>收款方式</strong>两个选项，<strong>只需配一种</strong>：</p>
        <table class="gd-table">
          <tr><th>选项</th><th>适合谁</th><th>客户端表现</th></tr>
          <tr><td><strong>聚合码</strong></td><td>有聚合收款码（一张码微信/支付宝都能扫）</td><td>只显示一张码，<strong>不出现</strong>支付方式选择</td></tr>
          <tr><td><strong>微信 / 支付宝分开</strong></td><td>只有个人收款码，没有聚合码</td><td><strong>先让客户选支付方式</strong>（💬 微信支付 / 🅰 支付宝），再显示对应的码</td></tr>
        </table>
        <ul style="margin-top:12px;">
          <li>选「聚合码」→ 只显示「聚合收款码地址」输入框，填那一张码的图片地址。</li>
          <li>选「分开」→ 显示「💬 微信收款码地址」+「🅰 支付宝收款码地址」，两张分别填。</li>
          <li>输入框会<strong>跟着选择自动显隐</strong>，不用管没选中的那个。</li>
          <li>选「分开」但<strong>只填了一张</strong>→ 客户端不显示选择器，直接用填的那张。</li>
        </ul>
        <div class="gd-tip">
          <strong>客户选了哪种支付方式，后台能看到。</strong>客户在付款弹窗里一点「💬 微信支付 / 🅰 支付宝」，
          系统就立刻记到订单上了 —— 到「订单管理」看<strong>「支付方式」列</strong>，
          或点订单「详情」看「支付方式」一行。聚合码模式下不用选，显示「聚合码」。
        </div>
        <div class="gd-note">切换模式后<strong>别忘点最下面「保存全部设置」</strong>，否则不生效。</div>
      </div>
    </details>

    <details class="gd-sec">
      <summary>🛍 客户端首页的「全部服务」按钮</summary>
      <div class="gd-body">
        <ul>
          <li>客户看到的商城首页是「按分类分组的卡片」；搜索框右边有一个蓝色<strong>「全部服务」</strong>按钮。</li>
          <li>点它进入<strong>分类页</strong>，默认停在「全部商品」，把所有商品平铺成一个网格，方便挑。</li>
          <li>分类页上方有一排分类按钮（全部 / 系统重装 / 驱动安装 …），点哪个只看哪一类，<strong>不会跳回首页</strong>。</li>
          <li>页面左上角「← 返回全部分类」可以回到首页。</li>
          <li>地址栏会同步成 <code>?cat=xxx</code>，可以直接收藏或分享某个分类的链接。</li>
        </ul>
      </div>
    </details>

    <details class="gd-sec">
      <summary>🔄 订单是怎么流动的</summary>
      <div class="gd-body">
        <table class="gd-table">
          <tr><th>状态</th><th>含义</th><th>你要做什么</th></tr>
          <tr><td>待付款</td><td>客户已提交，还没付款</td><td>等；超时会自动转「已失效」</td></tr>
          <tr><td>待人工核查</td><td>客户点了「我已付款」</td><td><strong>去收款账单核对备注码</strong>，确认到账后点「确认已付款」</td></tr>
          <tr><td>已确认付款</td><td>你已确认收到钱</td><td>点「开始处理」</td></tr>
          <tr><td>处理中</td><td>技术员正在服务</td><td>服务完成后点「标记完成」</td></tr>
          <tr><td>已完成</td><td>服务已交付</td><td>无需操作</td></tr>
          <tr><td>付款失败</td><td>标记为没收到钱</td><td>客户可在前台重新付款</td></tr>
          <tr><td>已失效</td><td>提交后一直没付款，自动失效</td><td>不计入统计，记录保留，可在「已失效」标签页查看</td></tr>
        </table>
        <div class="gd-tip">
          <strong>对账靠备注码</strong>：微信/支付宝个人收款码没有支付回调，只能靠客户在付款时填的「备注」核对。
          备注码就是唯一的对账锚点，默认 2 位、24 小时内不重复。
        </div>
      </div>
    </details>

    <details class="gd-sec">
      <summary>⏳ 未付款订单会自动失效吗</summary>
      <div class="gd-body">
        <ul>
          <li>超过设定时间（默认 <strong>10 分钟</strong>）仍没付款的订单，会自动标记成「<strong>已失效</strong>」。</li>
          <li>已失效订单：<strong>不计入统计、不占未付款名额、默认列表里不显示</strong>，但<strong>记录保留</strong> ——
              在订单管理点「已失效」标签页就能看到「谁下了单没付款」以及他填的全部信息。</li>
          <li>「已失效」订单默认保留 <strong>7 天</strong>后彻底删除，可在「收款与表单」里改（填 0 = 永久保留）。</li>
          <li>清理时机：打开订单列表时、客户下单时各顺手跑一次，<strong>不需要额外配计划任务</strong>。</li>
          <li><strong>客户一旦点过「我已付款」</strong>（状态变「待人工核查」），就<strong>永远不会被清掉</strong>，可以放心核对到账。</li>
        </ul>
        <div class="gd-note">想关闭这个功能：把「未付款订单自动失效」填 0。</div>
        <div class="gd-tip">
          <strong>⚠️ 这个时间别设太短。</strong>客户扫码付款要切到微信/支付宝、输金额、确认，
          再回来点「我已付款」，实际往往要 <strong>2~5 分钟</strong>。设成 5 分钟就会大量误伤正常付款的客户。<br>
          建议 <strong>30 分钟</strong>（默认值）。<br>
          <strong>好消息</strong>：即使订单已经变成「已失效」，客户再回来点「我已付款」<strong>仍然有效</strong> ——
          订单会自动复活成「待人工核查」并提醒你，不会把真付了钱的客户挡在门外。
        </div>
      </div>
    </details>

    <details class="gd-sec">
      <summary>🛡 客户被限流挡住 / 误封了怎么办</summary>
      <div class="gd-body">
        <p style="margin:0 0 8px;">系统会按 IP 自动限流（防止刷单和脚本）：</p>
        <table class="gd-table">
          <tr><th>项目</th><th>规则</th></tr>
          <tr><td>下单接口</td><td>同一 IP 每 10 分钟最多 8 次；另有 60 次/分钟总闸</td></tr>
          <tr><td>提交间隔</td><td>两次提交间隔不足 3 秒判定为脚本，直接拒绝</td></tr>
          <tr><td>未付款上限</td><td>同一 IP 24 小时内最多留 3 笔未付款订单</td></tr>
          <tr><td>订单查询</td><td>同一 IP 每 10 分钟最多 8 次</td></tr>
        </table>
        <ul style="margin-top:10px;">
          <li>限流<strong>最长 10 分钟自动解除</strong>；限流缓存文件失效满 1 小时会自动删掉。</li>
          <li><strong>真实客户被误封</strong>（比如他反复点了提交、或连下 3 单没付）：
              进「🛡 风控与清理」找到他的 IP，点「解除该 IP」，他就能立刻继续下单。</li>
          <li>「删除该 IP 的未付款单」会删掉他名下所有未付款订单，<strong>并同时解除限流</strong>。</li>
        </ul>
      </div>
    </details>

    <details class="gd-sec">
      <summary>🖼 收款码显示与二维码说明</summary>
      <div class="gd-body">
        <ul>
          <li>收款码按<strong>图片自身比例</strong>显示，不会有白边，也不强制正方形；点一下可放大到满屏方便扫码。</li>
          <li>后台预览上限约 170px、客户端付款弹窗约 240px 且不超过屏幕高度的 34%，竖长的海报式收款码也不会把页面撑得很长。</li>
          <li>如果你的收款码是<strong>透明背景</strong>的 PNG，深色模式下可能扫不出来 —— 换一张白底的即可。</li>
          <li>选了「微信 / 支付宝分开」时，客户端会出现「💬 微信支付 / 🅰 支付宝」两个按钮，<strong>点哪个就显示哪张码</strong>，点开放大也跟着切换。</li>
        </ul>
      </div>
    </details>

    <details class="gd-sec">
      <summary>🔔 消息推送怎么配</summary>
      <div class="gd-body">
        <ul>
          <li><strong>Gotify</strong>：自建最省事，装完在 App 里拿到 token，填服务器地址 + token。</li>
          <li><strong>ntfy</strong>：可用官方公共服务器（topic 名要够随机，相当于密码），也可自建。</li>
          <li><strong>邮件</strong>：填 SMTP 服务器、端口、账号、授权码。</li>
          <li><strong>通用 Webhook</strong>：任何能收 POST 的地址，支持自定义 JSON 模板。</li>
        </ul>
        <div class="gd-tip">可以同时开启多个通道。推送失败会自动进重试队列，不影响客户下单。</div>
        <p style="margin:12px 0 6px;"><strong>💻 我只想在电脑上收，怎么配？</strong></p>
        <table class="gd-table">
          <tr><th>通道</th><th>电脑上怎么收</th><th>难度</th></tr>
          <tr><td><strong>企业微信机器人</strong><br><span style="font-size:12.5px;">（用「通用 Webhook」）</span></td>
              <td>电脑装<strong>企业微信桌面版</strong>登录同一企业，机器人消息<strong>直接弹系统通知</strong>。零部署最省事。<br>
                  钉钉 / 飞书同理</td><td>★</td></tr>
          <tr><td><strong>邮件（SMTP）</strong></td>
              <td>任何邮箱客户端都行（Outlook / Foxmail / 网页邮箱），打开「新邮件桌面通知」就会弹窗</td><td>★</td></tr>
          <tr><td><strong>ntfy</strong></td>
              <td>① 浏览器打开 <code>https://你的服务器/你的topic</code> 挂着看<br>② 官方<strong>桌面 App</strong>（Win/macOS/Linux），能收系统通知</td><td>★★</td></tr>
          <tr><td><strong>Gotify</strong></td>
              <td>浏览器打开 Gotify 地址登录，<strong>WebUI 本身就是收件箱</strong>，新订单实时出现</td><td>★★★</td></tr>
        </table>
        <div class="gd-note">推荐：<strong>企业微信机器人 + 邮件</strong>两个一起开，互为备份，手机电脑都能收。</div>
        <p style="margin:12px 0 6px;"><strong>配好之后一定要做这一步</strong>：</p>
        <ul>
          <li>在「推送测试与重试」点对应的<strong>测试按钮</strong>，看手机能不能收到。</li>
          <li>没收到就展开那个通道检查参数；四个都失败可点「♻️ 立即处理重试队列」手动重试。</li>
          <li>「🗂 最近推送记录」点开可以看每一次推送的通道、HTTP 状态码和返回内容，<strong>排查问题全靠它</strong>；记录太多可以点「🗑 清理推送记录」清空。</li>
        </ul>
      </div>
    </details>

    <details class="gd-sec">
      <summary>🔔 后台自己盯着订单：横幅 + 电脑桌面通知</summary>
      <div class="gd-body">
        <p style="margin:0 0 10px;">除了「消息推送」（服务端主动发给你），<strong>订单管理页自己也会盯着</strong>：</p>
        <ul>
          <li>只要把「订单管理」页开着，<strong>有新订单 1 秒内就会提醒</strong> ——
              <strong>浏览器最小化、切到别的标签都照常提醒</strong>（用的是长轮询，不是定时器）。</li>
          <li>有两件事都会提醒：<strong>① 🛒 新订单</strong>（客户下单）、
              <strong>② 💰 客户已标记付款</strong>（客户点了「我已付款」，提示你去核对到账）。</li>
          <li>提醒时 → 右上角弹出<strong>绿色横幅</strong>（显示金额、客户、商品）。</li>
          <li>而且<strong>订单列表会自己刷新</strong>，不用手动按 F5 —— 你会看到顶部出现一条
              「有 N 笔订单变化，列表还没更新」，约 2 秒后自动刷新到位。<br>
              如果你正在看订单详情、勾着订单准备批量操作，它<strong>不会打断你</strong>，
              只显示提示条，等你操作完或点「立即刷新」。</li>
          <li>如果开了<strong>桌面通知</strong> → 同时弹出<strong>电脑系统的通知</strong>（切到别的程序也能看到）。</li>
        </ul>
        <p style="margin:12px 0 6px;"><strong>怎么开启桌面通知？</strong></p>
        <ol class="gd-steps">
          <li>打开「订单管理」，点搜索栏旁边的 <strong>🔔 桌面通知</strong> 按钮。</li>
          <li>浏览器会弹出「是否允许本站发送通知」→ 点<strong>允许</strong>。</li>
          <li>按钮变成 <strong>🔔 桌面通知已开启</strong>（绿色），同时会给你发一条测试通知 —— 收到就说明通了。</li>
        </ol>
        <div class="gd-tip">
          <strong>两个提醒的区别</strong>：<br>
          · <strong>消息推送</strong>（后台「消息推送」页）—— 服务端发给你，<strong>页面关着也能收到</strong>，手机电脑都能收。<br>
          　　它有两个触发时机（在「触发时机」里勾选）：<code>客户新下单</code>、<code>客户已标记付款</code>。<br>
          · <strong>桌面通知</strong>（本页）—— 浏览器发的，<strong>页面开着才有效</strong>，好处是零配置、点一下就行。<br>
          建议两个都开：推送保底，桌面通知让你在电脑前时第一时间看到。
        </div>
        <div class="gd-note">
          不小心点了「拒绝」？点浏览器地址栏左侧的<strong>锁 / 信息图标</strong> → 把「通知」改为「允许」→ 刷新页面即可。
        </div>
        <div class="gd-note" style="margin-top:8px;">
          ⚠️ <strong>桌面通知必须用 https:// 访问后台</strong>：浏览器只在 <code>https://</code>（或 <code>localhost</code>）页面提供通知能力。
          用 <code>http://</code> 或 <code>http://IP</code> 打开时按钮会显示「🔔 桌面通知不可用」（悬停可看原因）——
          这是浏览器限制，不是坏了。<strong>右上角横幅提醒不受影响，仍然可用。</strong>
        </div>
      </div>
    </details>
    <details class="gd-sec">
      <summary>🔑 账号与数据安全</summary>
      <div class="gd-body">
        <ul>
          <li><strong>改账号名 / 改密码</strong>：左侧栏「🔑 账号设置」。任一项改动都要先填原密码。</li>
          <li>账号名规则：2~32 位，只能用字母、数字、下划线、短横线。</li>
          <li>连续登录失败 5 次会锁定 10 分钟（防爆破）。</li>
          <li>后台页面会记录每一次写操作，见「🧾 操作日志」。</li>
        </ul>
        <p style="margin:12px 0 6px;"><strong>必须备份的东西</strong>（丢了无法恢复）：</p>
        <table class="gd-table">
          <tr><th>路径</th><th>内容</th></tr>
          <tr><td><code>data/shop.json</code></td><td>商品、分类、收款码、页面文案等全部配置（AES 加密）</td></tr>
          <tr><td><code>data/shop.db</code></td><td>订单数据（SQLite，含操作日志）</td></tr>
          <tr><td><code>data/account.php</code></td><td>后台账号与密码哈希</td></tr>
          <tr><td><code>secret/config.secret.php</code></td><td>加密密钥 —— <strong>丢了 shop.json 就解不开</strong></td></tr>
        </table>
        <div class="gd-note">服务器上务必确认 <code>data/</code> 与 <code>secret/</code> 目录对公网返回 403。</div>
      </div>
    </details>

    <details class="gd-sec">
      <summary>❓ 常见问题</summary>
      <div class="gd-body">
        <table class="gd-table">
          <tr><th>现象</th><th>原因 / 处理</th></tr>
          <tr><td>客户说看不到收款码</td><td>「收款与表单」里的收款码图片地址没填，或图片链接失效（建议用外链图床）</td></tr>
          <tr><td>收不到新订单提醒</td><td>推送通道没配好。去「消息推送」点「测试推送」看报错</td></tr>
          <tr><td>订单列表是空的</td><td>先看「全部」标签页；已失效的单在「已失效」标签页里</td></tr>
          <tr><td>客户说查不到自己的全部订单</td><td>查询页的「订单号」框会显示上次查的单号 —— 客户想查全部，把它<strong>清空</strong>再查即可（或点「🗂 查看我的全部订单」）</td></tr>
          <tr><td>查询结果里待付款的排在最后</td><td>不会了。查询结果<strong>待付款 / 付款失败的排在最前</strong>，方便客户先处理未付的单</td></tr>
          <tr><td>客户下单被拒「还有未完成付款的订单」</td><td>他 24 小时内留了 3 笔未付款单。等 10 分钟自动失效即可，或去「风控与清理」删掉</td></tr>
          <tr><td>客户下单被拒「访问过于频繁」</td><td>被限流了。去「风控与清理」解除他的 IP</td></tr>
          <tr><td>IP 归属地显示不准</td><td>免费 IP 库对部分网段本身就有分歧。可在订单详情点「归属地诊断」看各数据源分别给出什么</td></tr>
          <tr><td>改了样式但页面没变</td><td>强制刷新一次（<code>Ctrl + F5</code>）</td></tr>
          <tr><td>客户端付款时没让选微信/支付宝</td><td>收款方式还是「聚合码」。去「收款与表单」改成「分开的微信 / 支付宝收款码」并保存</td></tr>
          <tr><td>收款码显示不出来</td><td>图片地址填错或图床挂了。填的是<strong>图片直链</strong>（结尾是 .png/.jpg），不是网页地址</td></tr>
          <tr><td>某个配置项看不懂</td><td>配置项下方若有 <span style="color:#3b82f6;">▸ 蓝色小字</span>，点它就会展开详细说明，再点收起</td></tr>
        </table>
      </div>
    </details>

  </div>
</div>
