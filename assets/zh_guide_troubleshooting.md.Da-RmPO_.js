import{_ as e,c as o,o as a,a1 as t}from"./chunks/framework.gjrnbxUT.js";const b=JSON.parse('{"title":"故障排除","description":"","frontmatter":{},"headers":[],"relativePath":"zh/guide/troubleshooting.md","filePath":"zh/guide/troubleshooting.md"}'),r={name:"zh/guide/troubleshooting.md"},s=t('<h1 id="故障排除" tabindex="-1">故障排除 <a class="header-anchor" href="#故障排除" aria-label="Permalink to &quot;故障排除&quot;">​</a></h1><p>使用 static-php-cli 过程中可能会碰到各种各样的故障，这里将讲述如何自行查看错误并反馈 Issue。</p><h2 id="下载失败问题" tabindex="-1">下载失败问题 <a class="header-anchor" href="#下载失败问题" aria-label="Permalink to &quot;下载失败问题&quot;">​</a></h2><p>下载资源问题是 spc 最常见的问题之一。主要是由于 spc 下载资源使用的地址一般均为对应项目的官方网站或 GitHub 等，而这些网站可能偶尔会宕机、屏蔽 IP 地址。 目前 2.0.0 版本还没有加入自动重试机制，所以在遇到下载失败后，可以多次尝试调用下载命令。如果确认地址确实无法正常访问，可以提交 Issue 或 PR 更新地址。</p><h2 id="doctor-无法修复" tabindex="-1">doctor 无法修复 <a class="header-anchor" href="#doctor-无法修复" aria-label="Permalink to &quot;doctor 无法修复&quot;">​</a></h2><p>在绝大部分情况下，doctor 模块都可以对缺失的系统环境进行自动修复和安装，但也存在特殊的环境无法正常使用自动修复功能。</p><p>部分项目由于系统局限（如 Windows 下无法自动安装 Visual Studio 等软件），无法使用自动修复功能。 在遇到无法自动修复功能时，如果遇到 <code>Some check items can not be fixed</code> 字样，则表明无法自动修复，请根据终端显示的方法提交 Issue 或自行修复环境。</p><h2 id="编译错误" tabindex="-1">编译错误 <a class="header-anchor" href="#编译错误" aria-label="Permalink to &quot;编译错误&quot;">​</a></h2><p>遇到编译错误时，如果没有开启 <code>--debug</code> 日志，请先开启调试日志，然后确定报错的命令。 报错的终端输出对于修复编译错误非常重要，请在提交 Issue 时一并将终端日志的最后报错片段（或整个终端日志输出）上传，并且包含使用的 <code>spc</code> 命令和参数。</p>',9),c=[s];function i(d,n,h,l,u,_){return a(),o("div",null,c)}const m=e(r,[["render",i]]);export{b as __pageData,m as default};