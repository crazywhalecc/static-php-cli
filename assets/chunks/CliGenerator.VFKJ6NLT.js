import{d as we,s as w,h as D,v as G,o as g,c as x,j as e,t as n,F as N,E as O,a2 as m,a3 as R,a4 as Y,e as I,a5 as f,a as ge,a6 as F,p as xe,l as me,_ as ye}from"./framework.gjrnbxUT.js";const he={support:{BSD:"wip"},type:"external","arg-type":"custom",source:"amqp","lib-depends":["librabbitmq"],"ext-depends-windows":["openssl"]},ve={type:"external",source:"apcu"},fe={type:"external",source:"ast"},ze={type:"builtin"},Se={type:"builtin","arg-type-unix":"with-prefix","arg-type-windows":"with","lib-depends":["bzip2"]},De={type:"builtin"},ke={type:"builtin"},Be={notes:!0,type:"builtin","arg-type":"with","lib-depends":["curl"],"ext-depends-windows":["zlib","openssl"]},qe={type:"builtin","arg-type":"custom","lib-suggests":["qdbm"]},Ee={support:{BSD:"wip"},type:"external",source:"dio"},We={support:{BSD:"wip"},type:"builtin","arg-type":"custom","arg-type-windows":"with","lib-depends":["libxml2","zlib"],"ext-depends-windows":["xml"]},Ce={type:"external",source:"ext-ds"},Ie={support:{Windows:"wip",BSD:"wip",Darwin:"wip",Linux:"wip"},type:"wip"},Pe={support:{Windows:"wip",BSD:"wip"},notes:!0,type:"external",source:"ext-event","arg-type":"custom","lib-depends":["libevent"],"ext-depends":["openssl"],"ext-suggests":["sockets"]},_e={type:"builtin"},Ue={support:{Linux:"partial",BSD:"wip"},notes:!0,"arg-type":"custom",type:"builtin","lib-depends-unix":["libffi"],"lib-depends-windows":["libffi-win"]},Le={type:"builtin"},$e={type:"builtin"},Ne={type:"builtin","lib-suggests":["openssl"]},Oe={support:{BSD:"wip"},notes:!0,type:"builtin","arg-type":"custom","arg-type-windows":"with","lib-depends":["zlib","libpng"],"ext-depends":["zlib"],"lib-suggests":["libavif","libwebp","libjpeg","freetype"]},Ve={support:{Windows:"wip",BSD:"wip"},type:"builtin","arg-type":"with-prefix","lib-depends":["gettext"]},Ae={support:{Windows:"wip",BSD:"no",Linux:"no"},notes:!0,type:"external","arg-type":"custom",source:"ext-glfw","lib-depends":["glfw"],"lib-depends-windows":[]},Te={support:{Windows:"wip",BSD:"wip"},type:"builtin","arg-type":"with-prefix","lib-depends":["gmp"]},je={support:{BSD:"wip"},type:"external",source:"ext-gmssl","lib-depends":["gmssl"]},Ge={support:{Windows:"wip",BSD:"wip"},type:"external",source:"grpc","arg-type-unix":"custom","cpp-extension":!0,"lib-depends":["grpc"]},Xe={support:{BSD:"wip"},type:"builtin","arg-type":"with-prefix","arg-type-windows":"with","lib-depends-unix":["libiconv"],"lib-depends-windows":["libiconv-win"]},Me={support:{BSD:"wip"},type:"external",source:"igbinary","ext-suggests":["session","apcu"]},He={support:{Windows:"wip",BSD:"wip"},type:"external",source:"ext-imagick","arg-type":"custom","lib-depends":["imagemagick"]},Re={support:{Windows:"wip",BSD:"wip"},notes:!0,type:"external",source:"ext-imap","arg-type":"custom","lib-depends":["imap"],"ext-suggests":["openssl"]},Fe={support:{Windows:"no",BSD:"wip",Darwin:"no"},type:"external",source:"inotify"},Ze={support:{Windows:"no",BSD:"wip"},type:"builtin","lib-depends":["icu"]},Qe={support:{Windows:"wip",BSD:"wip"},type:"builtin","arg-type":"with-prefix","lib-depends":["ldap"],"lib-suggests":["gmp","libsodium"],"ext-suggests":["openssl"]},Ke={support:{BSD:"wip"},type:"builtin","arg-type":"none","ext-depends":["xml"]},Ye={type:"builtin","arg-type":"custom","ext-depends":["mbstring"],"lib-depends":["onig"]},Je={type:"builtin","arg-type":"custom"},ei={type:"wip",support:{Windows:"no",BSD:"no",Darwin:"no",Linux:"no"},notes:!0},ii={support:{Windows:"wip",BSD:"wip"},type:"external",source:"ext-memcache","arg-type":"custom","ext-depends":["zlib","session"]},si={support:{Windows:"wip",BSD:"wip",Linux:"no"},type:"external",source:"memcached","arg-type":"custom","cpp-extension":!0,"lib-depends":["libmemcached"],"ext-depends":["session","zlib"]},li={support:{BSD:"wip",Windows:"wip"},type:"external",source:"mongodb","arg-type":"custom","lib-suggests":["icu","openssl","zstd","zlib"]},ti={support:{BSD:"wip"},type:"external",source:"msgpack","arg-type-unix":"with","arg-type-win":"enable"},ni={type:"builtin","arg-type":"with","ext-depends":["mysqlnd"]},oi={type:"builtin","arg-type-windows":"with","lib-depends":["zlib"]},ai={type:"wip",support:{Windows:"wip",BSD:"no",Darwin:"no",Linux:"no"},notes:!0},di={type:"builtin","arg-type-unix":"custom"},ui={notes:!0,type:"builtin","arg-type":"custom","arg-type-windows":"with","lib-depends":["openssl","zlib"],"ext-depends":["zlib"]},pi={support:{BSD:"wip"},type:"external",source:"opentelemetry"},ri={support:{BSD:"wip"},notes:!0,type:"external",source:"parallel","arg-type-windows":"with","lib-depends-windows":["pthreads4w"]},ci={support:{Windows:"no"},type:"builtin","unix-only":!0},bi={type:"builtin"},wi={type:"builtin","arg-type":"with","ext-depends":["pdo","mysqlnd"]},gi={support:{Windows:"wip",BSD:"wip"},type:"builtin","arg-type":"with-prefix","ext-depends":["pdo","pgsql"],"lib-depends":["postgresql"]},xi={support:{BSD:"wip"},type:"builtin","arg-type":"with","ext-depends":["pdo","sqlite3"],"lib-depends":["sqlite"]},mi={support:{BSD:"wip"},type:"external",source:"pdo_sqlsrv","arg-type":"with","ext-depends":["pdo","sqlsrv"]},yi={support:{Windows:"wip",BSD:"wip"},notes:!0,type:"builtin","arg-type":"custom","lib-depends":["postgresql"]},hi={type:"builtin","ext-depends":["zlib"]},vi={support:{Windows:"no"},type:"builtin","unix-only":!0},fi={support:{Windows:"wip",BSD:"wip"},type:"external",source:"protobuf"},zi={support:{BSD:"wip",Darwin:"partial"},notes:!0,type:"external",source:"rar","cpp-extension":!0},Si={support:{BSD:"wip",Windows:"wip"},type:"external",source:"ext-rdkafka","arg-type":"custom","cpp-extension":!0,"lib-depends":["librdkafka"]},Di={support:{Windows:"wip",BSD:"wip"},type:"builtin","arg-type":"with-prefix","lib-depends":["readline"]},ki={support:{BSD:"wip"},type:"external",source:"redis","arg-type":"custom","ext-suggests":["session","igbinary"],"lib-suggests-unix":["zstd","liblz4"]},Bi={type:"builtin"},qi={type:"builtin"},Ei={type:"external",source:"ext-simdjson","cpp-extension":!0},Wi={support:{BSD:"wip"},type:"builtin","arg-type":"custom","lib-depends":["libxml2"],"ext-depends-windows":["xml"]},Ci={support:{Windows:"wip",BSD:"wip"},type:"external",source:"ext-snappy","cpp-extension":!0,"arg-type":"custom","lib-depends":["snappy"],"ext-suggests":["apcu"]},Ii={support:{BSD:"wip"},type:"builtin","arg-type":"custom","lib-depends":["libxml2"],"ext-depends-windows":["xml"]},Pi={type:"builtin"},_i={support:{BSD:"wip"},type:"builtin","arg-type":"with","lib-depends":["libsodium"]},Ui={support:{BSD:"wip",Windows:"no"},notes:!0,type:"external",source:"spx","arg-type":"custom","lib-depends":["zlib"]},Li={support:{BSD:"wip"},type:"builtin","arg-type":"with-prefix","arg-type-windows":"with","lib-depends":["sqlite"]},$i={support:{BSD:"wip"},type:"external",source:"sqlsrv","lib-depends-unix":["unixodbc"],"ext-depends-linux":["pcntl"],"cpp-extension":!0},Ni={support:{BSD:"wip"},type:"external",source:"ext-ssh2","arg-type":"with-prefix","arg-type-windows":"with","lib-depends":["libssh2"],"ext-depends-windows":["openssl","zlib"]},Oi={support:{Windows:"no",BSD:"wip"},notes:!0,type:"external",source:"swoole","arg-type":"custom","cpp-extension":!0,"unix-only":!0,"lib-depends":["libcares","brotli","nghttp2","zlib"],"ext-depends":["openssl","curl"],"ext-suggests":["swoole-hook-pgsql","swoole-hook-mysql","swoole-hook-sqlite"]},Vi={support:{BSD:"wip"},notes:!0,type:"external",source:"swow","arg-type":"custom","lib-suggests":["openssl","curl"],"ext-suggests":["openssl","curl"]},Ai={support:{Windows:"no",BSD:"wip"},type:"builtin","unix-only":!0},Ti={support:{Windows:"no",BSD:"wip"},type:"builtin","unix-only":!0},ji={support:{BSD:"wip"},type:"builtin"},Gi={support:{Windows:"wip",BSD:"wip"},type:"builtin","arg-type":"with-prefix","lib-depends":["tidy"]},Xi={type:"builtin"},Mi={support:{Windows:"wip",BSD:"wip"},type:"external",source:"ext-uuid","arg-type":"with-prefix","lib-depends":["libuuid"]},Hi={support:{Windows:"wip",BSD:"wip"},type:"external",source:"ext-uv","arg-type":"with-prefix","lib-depends":["libuv"],"ext-depends":["sockets"]},Ri={type:"builtin",support:{Windows:"wip",BSD:"no",Darwin:"no",Linux:"no"},notes:!0},Fi={support:{Windows:"wip",BSD:"wip"},notes:!0,type:"external",source:"xhprof","ext-depends":["ctype"]},Zi={support:{Windows:"wip",BSD:"wip"},type:"external",source:"xlswriter","arg-type":"custom","ext-depends":["zlib","zip"],"lib-suggests":["openssl"]},Qi={support:{BSD:"wip"},notes:!0,type:"builtin","arg-type":"custom","arg-type-windows":"with","lib-depends":["libxml2"],"ext-depends-windows":["iconv"]},Ki={support:{BSD:"wip"},type:"builtin","arg-type":"custom","lib-depends":["libxml2"],"ext-depends-windows":["xml","dom"]},Yi={support:{BSD:"wip"},type:"builtin","arg-type":"custom","lib-depends":["libxml2"],"ext-depends-windows":["xml"]},Ji={support:{Windows:"wip",BSD:"wip"},type:"builtin","arg-type":"with-prefix","lib-depends":["libxslt"],"ext-depends":["xml","dom"]},es={support:{BSD:"wip"},type:"external",source:"yac","arg-type-unix":"custom","ext-depends-unix":["igbinary"]},is={support:{BSD:"wip"},type:"external",source:"yaml","arg-type-unix":"with-prefix","arg-type-windows":"with","lib-depends":["libyaml"]},ss={support:{BSD:"wip"},type:"builtin","arg-type":"with-prefix","arg-type-windows":"enable","lib-depends-unix":["libzip"],"ext-depends-windows":["zlib","bz2"],"lib-depends-windows":["libzip","zlib","bzip2","xz"]},ls={type:"builtin","arg-type":"custom","arg-type-windows":"enable","lib-depends":["zlib"]},ts={support:{Windows:"wip",BSD:"wip"},type:"external",source:"ext-zstd","arg-type":"custom","lib-depends":["zstd"]},ns={amqp:he,apcu:ve,ast:fe,bcmath:ze,bz2:Se,calendar:De,ctype:ke,curl:Be,dba:qe,dio:Ee,dom:We,ds:Ce,enchant:Ie,event:Pe,exif:_e,ffi:Ue,fileinfo:Le,filter:$e,ftp:Ne,gd:Oe,gettext:Ve,glfw:Ae,gmp:Te,gmssl:je,grpc:Ge,iconv:Xe,igbinary:Me,imagick:He,imap:Re,inotify:Fe,intl:Ze,ldap:Qe,libxml:Ke,mbregex:Ye,mbstring:Je,mcrypt:ei,memcache:ii,memcached:si,mongodb:li,msgpack:ti,mysqli:ni,mysqlnd:oi,oci8:ai,opcache:di,openssl:ui,opentelemetry:pi,parallel:ri,"password-argon2":{support:{Windows:"wip",BSD:"wip"},notes:!0,type:"builtin","arg-type":"with-prefix","lib-depends":["libargon2"]},pcntl:ci,pdo:bi,pdo_mysql:wi,pdo_pgsql:gi,pdo_sqlite:xi,pdo_sqlsrv:mi,pgsql:yi,phar:hi,posix:vi,protobuf:fi,rar:zi,rdkafka:Si,readline:Di,redis:ki,session:Bi,shmop:qi,simdjson:Ei,simplexml:Wi,snappy:Ci,soap:Ii,sockets:Pi,sodium:_i,spx:Ui,sqlite3:Li,sqlsrv:$i,ssh2:Ni,swoole:Oi,"swoole-hook-mysql":{support:{Windows:"no",BSD:"wip"},notes:!0,type:"addon","arg-type":"custom","ext-depends":["mysqlnd","pdo","pdo_mysql"],"ext-suggests":["mysqli"]},"swoole-hook-pgsql":{support:{Windows:"no",BSD:"wip",Darwin:"partial"},notes:!0,type:"addon","arg-type":"custom","ext-depends":["pgsql","pdo"]},"swoole-hook-sqlite":{support:{Windows:"no",BSD:"wip"},notes:!0,type:"addon","arg-type":"custom","ext-depends":["sqlite3","pdo"]},swow:Vi,sysvmsg:Ai,sysvsem:Ti,sysvshm:ji,tidy:Gi,tokenizer:Xi,uuid:Mi,uv:Hi,xdebug:Ri,xhprof:Fi,xlswriter:Zi,xml:Qi,xmlreader:Ki,xmlwriter:Yi,xsl:Ji,yac:es,yaml:is,zip:ss,zlib:ls,zstd:ts},os={type:"root",source:"php-src","lib-depends":["lib-base","micro"],"lib-suggests-linux":["libacl"]},as={type:"target",source:"micro"},ds={source:"attr","static-libs-unix":["libattr.a"]},us={source:"brotli","static-libs-unix":["libbrotlidec.a","libbrotlienc.a","libbrotlicommon.a"],"static-libs-windows":["brotlicommon.lib","brotlienc.lib","brotlidec.lib"],headers:["brotli"]},ps={source:"bzip2","static-libs-unix":["libbz2.a"],"static-libs-windows":["libbz2.lib","libbz2_a.lib"],headers:["bzlib.h"]},rs={source:"curl","static-libs-unix":["libcurl.a"],"static-libs-windows":["libcurl.lib"],headers:["curl"],"lib-depends-unix":["openssl","zlib"],"lib-depends-windows":["openssl","zlib","libssh2","nghttp2"],"lib-suggests-unix":["libssh2","brotli","nghttp2","zstd","libcares"],"lib-suggests-windows":["brotli","zstd"],frameworks:["CoreFoundation","CoreServices","SystemConfiguration"]},cs={source:"freetype","static-libs-unix":["libfreetype.a"],"static-libs-windows":["libfreetype_a.lib"],"headers-unix":["freetype2/freetype/freetype.h","freetype2/ft2build.h"],"lib-depends":["zlib"],"lib-suggests":["libpng","bzip2","brotli"]},bs={source:"gettext","static-libs-unix":["libintl.a"],"lib-depends":["libiconv"],"lib-suggests":["ncurses","libxml2"],frameworks:["CoreFoundation"]},ws={source:"ext-glfw","static-libs-unix":["libglfw3.a"],frameworks:["CoreVideo","OpenGL","Cocoa","IOKit"]},gs={source:"gmp","static-libs-unix":["libgmp.a"],"static-libs-windows":["libgmp.lib"],headers:["gmp.h"]},xs={source:"gmssl","static-libs-unix":["libgmssl.a"],"static-libs-windows":["gmssl.lib"],frameworks:["Security"]},ms={source:"grpc","static-libs-unix":["libgrpc.a","libcares.a"],"lib-depends":["zlib","openssl"],frameworks:["CoreFoundation"]},ys={source:"icu","cpp-library":!0,"static-libs-unix":["libicui18n.a","libicuio.a","libicuuc.a","libicudata.a"]},hs={source:"imagemagick","static-libs-unix":["libMagick++-7.Q16HDRI.a","libMagickWand-7.Q16HDRI.a","libMagickCore-7.Q16HDRI.a"],"lib-depends":["zlib","libpng","libjpeg","libwebp","freetype","libtiff","libheif"],"lib-suggests":["zstd","xz","bzip2","libzip","libxml2"]},vs={source:"imap","static-libs-unix":["libc-client.a"],"lib-suggests":["openssl"]},fs={source:"ldap","static-libs-unix":["liblber.a","libldap.a"],"lib-depends":["openssl","zlib","gmp","libsodium"]},zs={source:"libacl","static-libs-unix":["libacl.a"],"lib-depends":["attr"]},Ss={source:"libaom","static-libs-unix":["libaom.a"],"cpp-library":!0},Ds={source:"libargon2","static-libs-unix":["libargon2.a"]},ks={source:"libavif","static-libs-unix":["libavif.a"],"static-libs-windows":["avif.lib"]},Bs={source:"libcares","static-libs-unix":["libcares.a"],"headers-unix":["ares.h","ares_dns.h","ares_nameser.h","ares_rules.h"]},qs={source:"libde265","static-libs-unix":["libde265.a"],"cpp-library":!0},Es={source:"libevent","static-libs-unix":["libevent.a","libevent_core.a","libevent_extra.a","libevent_openssl.a"],"lib-depends":["openssl"]},Ws={source:"libffi","static-libs-unix":["libffi.a"],"static-libs-windows":["libffi.lib"],"headers-unix":["ffi.h","ffitarget.h"],"headers-windows":["ffi.h","fficonfig.h","ffitarget.h"]},Cs={source:"libheif","static-libs-unix":["libheif.a"],"lib-depends":["libde265","libwebp","libaom","zlib","brotli"]},Is={source:"libiconv","static-libs-unix":["libiconv.a","libcharset.a"],headers:["iconv.h","libcharset.h","localcharset.h"]},Ps={source:"libjpeg","static-libs-unix":["libjpeg.a","libturbojpeg.a"],"static-libs-windows":["libjpeg_a.lib"],"lib-suggests-windows":["zlib"]},_s={source:"liblz4","static-libs-unix":["liblz4.a"]},Us={source:"libmemcached","static-libs-unix":["libmemcached.a","libmemcachedutil.a"]},Ls={source:"libpng","static-libs-unix":["libpng16.a"],"static-libs-windows":["libpng16_static.lib","libpng_a.lib"],"headers-unix":["png.h","pngconf.h","pnglibconf.h"],"headers-windows":["png.h","pngconf.h"],"lib-depends":["zlib"]},$s={source:"librabbitmq","static-libs-unix":["librabbitmq.a"],"static-libs-windows":["rabbitmq.4.lib"],"lib-depends":["openssl"]},Ns={source:"librdkafka","static-libs-unix":["librdkafka.a","librdkafka++.a","librdkafka-static.a"],"cpp-library":!0,"lib-suggests":["zstd"]},Os={source:"libsodium","static-libs-unix":["libsodium.a"],"static-libs-windows":["libsodium.lib"]},Vs={source:"libssh2","static-libs-unix":["libssh2.a"],"static-libs-windows":["libssh2.lib"],headers:["libssh2.h","libssh2_publickey.h","libssh2_sftp.h"],"lib-depends":["openssl"],"lib-suggests":["zlib"]},As={source:"libtiff","static-libs-unix":["libtiff.a"],"lib-depends":["zlib","libjpeg"]},Ts={source:"libuuid","static-libs-unix":["libuuid.a"],headers:["uuid/uuid.h"]},js={source:"libuv","static-libs-unix":["libuv.a"]},Gs={source:"libwebp","static-libs-unix":["libwebp.a","libwebpdecoder.a","libwebpdemux.a","libwebpmux.a","libsharpyuv.a"],"static-libs-windows":["libwebp.lib","libwebpdecoder.lib","libwebpdemux.lib","libsharpyuv.lib"]},Xs={source:"libxml2","static-libs-unix":["libxml2.a"],"static-libs-windows":["libxml2s.lib","libxml2_a.lib"],headers:["libxml2"],"lib-depends-unix":["libiconv"],"lib-suggests-unix":["xz","icu","zlib"],"lib-depends-windows":["libiconv-win"],"lib-suggests-windows":["zlib"]},Ms={source:"libxslt","static-libs-unix":["libxslt.a","libexslt.a"],"lib-depends":["libxml2"]},Hs={source:"libyaml","static-libs-unix":["libyaml.a"],"static-libs-windows":["yaml.lib"],headers:["yaml.h"]},Rs={source:"libzip","static-libs-unix":["libzip.a"],"static-libs-windows":["zip.lib","libzip_a.lib"],headers:["zip.h","zipconf.h"],"lib-depends-unix":["zlib"],"lib-suggests-unix":["bzip2","xz","zstd","openssl"],"lib-depends-windows":["zlib","bzip2","xz"],"lib-suggests-windows":["zstd","openssl"]},Fs={source:"ncurses","static-libs-unix":["libncurses.a"]},Zs={source:"nghttp2","static-libs-unix":["libnghttp2.a"],"static-libs-windows":["nghttp2.lib"],headers:["nghttp2"],"lib-depends":["zlib","openssl"],"lib-suggests":["libxml2"]},Qs={source:"onig","static-libs-unix":["libonig.a"],"static-libs-windows":["onig.lib","onig_a.lib"],headers:["oniggnu.h","oniguruma.h"]},Ks={source:"openssl","static-libs-unix":["libssl.a","libcrypto.a"],"static-libs-windows":["libssl.lib","libcrypto.lib"],headers:["openssl"],"lib-depends":["zlib"]},Ys={source:"postgresql","static-libs-unix":["libpq.a","libpgport.a","libpgcommon.a"],"lib-depends":["libiconv","libxml2","openssl","zlib","readline"],"lib-suggests":["icu","libxslt","ldap","zstd"]},Js={source:"pthreads4w","static-libs-windows":["libpthreadVC3.lib"]},el={source:"qdbm","static-libs-unix":["libqdbm.a"],"static-libs-windows":["qdbm_a.lib"],"headers-windows":["depot.h"]},il={source:"readline","static-libs-unix":["libreadline.a"],"lib-depends":["ncurses"]},sl={source:"snappy","static-libs-unix":["libsnappy.a"],"headers-unix":["snappy.h","snappy-c.h","snappy-sinksource.h","snappy-stubs-public.h"],"lib-depends":["zlib"]},ll={source:"sqlite","static-libs-unix":["libsqlite3.a"],"static-libs-windows":["libsqlite3_a.lib"],headers:["sqlite3.h","sqlite3ext.h"]},tl={source:"tidy","static-libs-unix":["libtidy.a"]},nl={source:"unixodbc","static-libs-unix":["libodbc.a","libodbccr.a","libodbcinst.a"],"lib-depends":["libiconv"]},ol={source:"xz","static-libs-unix":["liblzma.a"],"static-libs-windows":["lzma.lib","liblzma_a.lib"],"headers-unix":["lzma"],"headers-windows":["lzma","lzma.h"],"lib-depends-unix":["libiconv"]},al={source:"zlib","static-libs-unix":["libz.a"],"static-libs-windows":["zlib_a.lib"],headers:["zlib.h","zconf.h"]},dl={source:"zstd","static-libs-unix":["libzstd.a"],"static-libs-windows":[["zstd.lib","zstd_static.lib"]],"headers-unix":["zdict.h","zstd.h","zstd_errors.h"],"headers-windows":["zstd.h","zstd_errors.h"]},ul={"lib-base":{type:"root","lib-depends-unix":["pkg-config"]},php:os,micro:as,"pkg-config":{type:"package",source:"pkg-config","bin-unix":["pkg-config"]},attr:ds,brotli:us,bzip2:ps,curl:rs,freetype:cs,gettext:bs,glfw:ws,gmp:gs,gmssl:xs,grpc:ms,icu:ys,imagemagick:hs,imap:vs,ldap:fs,libacl:zs,libaom:Ss,libargon2:Ds,libavif:ks,libcares:Bs,libde265:qs,libevent:Es,libffi:Ws,"libffi-win":{source:"libffi-win","static-libs-windows":["libffi.lib"],"headers-windows":["ffi.h","ffitarget.h","fficonfig.h"]},libheif:Cs,libiconv:Is,"libiconv-win":{source:"libiconv-win","static-libs-windows":["libiconv.lib","libiconv_a.lib"]},libjpeg:Ps,liblz4:_s,libmemcached:Us,libpng:Ls,librabbitmq:$s,librdkafka:Ns,libsodium:Os,libssh2:Vs,libtiff:As,libuuid:Ts,libuv:js,libwebp:Gs,libxml2:Xs,libxslt:Ms,libyaml:Hs,libzip:Rs,ncurses:Fs,nghttp2:Zs,onig:Qs,openssl:Ks,postgresql:Ys,pthreads4w:Js,qdbm:el,readline:il,snappy:sl,sqlite:ll,tidy:tl,unixodbc:nl,xz:ol,zlib:al,zstd:dl};function _(i,t,d,a){return i.os==="linux"?i[t][d][a+"-linux"]??i[t][d][a+"-unix"]??i[t][d][a]??[]:i.os==="macos"?i[t][d][a+"-macos"]??i[t][d][a+"-unix"]??i[t][d][a]??[]:i.os==="windows"?i[t][d][a+"-windows"]??i[t][d][a]??[]:[]}function J(i,t){return _(i,"ext",t,"ext-depends")}function pl(i,t){return _(i,"ext",t,"ext-suggests")}function rl(i,t){return _(i,"ext",t,"lib-depends")}function cl(i,t){return _(i,"ext",t,"lib-suggests")}function ee(i,t){return _(i,"lib",t,"lib-depends")}function bl(i,t){return _(i,"lib",t,"lib-suggests")}function wl(i,t){const d=[],a=new Set,y=[];t.forEach(S=>{a.has(S)||ml(i,S,a,d)});const z=[];return d.forEach(S=>{t.indexOf(S)===-1&&y.push(S),[...rl(i,S),...cl(i,S)].forEach(o=>{z.indexOf(o)===-1&&z.push(o)})}),{exts:d,libs:gl(i,z),notIncludedExts:y}}function gl(i,t){const d=[],a=new Set;return t.forEach(y=>{a.has(y)||(console.log("before visited"),console.log(a),xl(i,y,a,d),console.log("after visited"),console.log(a))}),d}function xl(i,t,d,a){if(d.has(t))return;d.add(t),[...ee(i,t),...bl(i,t)].forEach(z=>{ie(i,z,d,a)}),a.push(t)}function ie(i,t,d,a){d.has(t)||(d.add(t),ee(i,t).forEach(y=>{ie(i,y,d,a)}),a.push(t))}function se(i,t,d,a){d.has(d)||(d.add(t),J(i,t).forEach(y=>{se(i,y,d,a)}),a.push(t))}function ml(i,t,d,a){if(d.has(t))return;d.add(t),[...J(i,t),...pl(i,t)].forEach(z=>{se(i,z,d,a)}),a.push(t)}const E=i=>(xe("data-v-36cf2ac6"),i=i(),me(),i),yl={class:"option-line"},hl=["id","value","disabled"],vl=["for"],fl={class:"option-line"},zl=E(()=>e("option",{value:"x86_64"},"x86_64",-1)),Sl=["disabled"],Dl={class:"box"},kl=E(()=>e("br",null,null,-1)),Bl={class:"ext-item"},ql=["id","value","disabled"],El=["for"],Wl={style:{color:"orangered","font-weight":"bolder"}},Cl={class:"details custom-block",open:""},Il={class:"box"},Pl={class:"ext-item"},_l=["id","value","disabled"],Ul=["for"],Ll={class:"tip custom-block"},$l=E(()=>e("p",{class:"custom-block-title"},"TIP",-1)),Nl={class:"box"},Ol={class:"ext-item"},Vl=["id","value"],Al=["for"],Tl={key:1,class:"warning custom-block"},jl=E(()=>e("p",{class:"custom-block-title"},"WARNING",-1)),Gl={key:2,class:"warning custom-block"},Xl=E(()=>e("p",{class:"custom-block-title"},"WARNING",-1)),Ml={value:"native"},Hl={value:"spc"},Rl={key:0,value:"docker"},Fl=["value"],Zl={for:"debug-yes"},Ql={for:"debug-no"},Kl={for:"zts-yes"},Yl={for:"zts-no"},Jl={for:"show-download-yes"},et={for:"show-download-no"},it={for:"pre-built-yes"},st={for:"pre-built-no"},lt={key:0},tt={for:"upx-yes"},nt={for:"upx-no"},ot=["placeholder"],at={key:3,class:"command-container"},dt={key:0,class:"command-preview"},ut=E(()=>e("br",null,null,-1)),pt={key:1},rt={class:"warning custom-block"},ct=E(()=>e("p",{class:"custom-block-title"},"WARNING",-1)),bt=E(()=>e("a",{href:"https://dl.static-php.dev/static-php-cli/spc-bin/nightly/spc-windows-x64.exe",target:"_blank"},"https://dl.static-php.dev/static-php-cli/spc-bin/nightly/spc-windows-x64.exe",-1)),wt={key:4,class:"command-container"},gt={id:"download-ext-cmd",class:"command-preview"},xt={key:5,class:"command-container"},mt={id:"download-all-cmd",class:"command-preview"},yt={key:6,class:"command-container"},ht={id:"download-pkg-cmd",class:"command-preview"},vt={class:"command-container"},ft={id:"build-cmd",class:"command-preview"},zt={name:"CliGenerator"},St=we({...zt,props:{lang:{type:String,default:"zh"}},setup(i){const t=w(ns),d=D(()=>{const p=[];for(const[l,s]of Object.entries(t.value))S(l,r.value)&&p.push(l);return p}),a=w(ul),y=w([]),z=[{os:"linux",label:"Linux",disabled:!1},{os:"macos",label:"macOS",disabled:!1},{os:"windows",label:"Windows",disabled:!1}],S=(p,l)=>{var b,q;const c=new Map([["linux","Linux"],["macos","Darwin"],["windows","Windows"]]).get(l),u=((q=(b=t.value[p])==null?void 0:b.support)==null?void 0:q[c])??"yes";return u==="yes"||u==="partial"},Z=["8.0","8.1","8.2","8.3","8.4"],o={zh:{selectExt:"选择扩展",buildTarget:"选择编译目标",buildOptions:"编译选项",buildEnvironment:"编译环境",buildEnvNative:"本地构建（Git 源码）",buildEnvSpc:"本地构建（独立 spc 二进制）",buildEnvDocker:"Alpine Docker 构建",useDebug:"是否开启调试输出",yes:"是",no:"否",resultShow:"结果展示",selectCommon:"选择常用扩展",selectNone:"全部取消选择",useZTS:"是否编译线程安全版",hardcodedINI:"硬编码 INI 选项",hardcodedINIPlacehoder:"如需要硬编码 ini，每行写一个，例如：memory_limit=2G",resultShowDownload:"是否展示仅下载对应扩展依赖的命令",downloadExtOnlyCommand:"只下载对应扩展的依赖包命令",downloadAllCommand:"下载所有依赖包命令",downloadUPXCommand:"下载 UPX 命令",compileCommand:"编译命令",downloadPhpVersion:"下载 PHP 版本",downloadSPCBinaryCommand:"下载 spc 二进制命令",selectedArch:"选择系统架构",selectedSystem:"选择操作系统",buildLibs:"要构建的库",depTips:"选择扩展后，不可选中的项目为必需的依赖，编译的依赖库列表中可选的为现有扩展和依赖库的可选依赖。选择可选依赖后，将生成 --with-libs 参数。",microUnavailable:"micro 不支持 PHP 7.4 及更早版本！",windowsSAPIUnavailable:"Windows 目前不支持 fpm、embed 构建！",useUPX:"是否开启 UPX 压缩（减小二进制体积）",windowsDownSPCWarning:"Windows 下请手动下载 spc.exe 二进制文件，解压到当前目录并重命名为 spc.exe！",usePreBuilt:"如果可能，下载预编译的依赖库（减少编译时间）"},en:{selectExt:"Select Extensions",buildTarget:"Build Target",buildOptions:"Build Options",buildEnvironment:"Build Environment",buildEnvNative:"Native build (Git source code)",buildEnvSpc:"Native build (standalone spc binary)",buildEnvDocker:"Alpine docker build",useDebug:"Enable debug message",yes:"Yes",no:"No",resultShow:"Result",selectCommon:"Select common extensions",selectNone:"Unselect all",useZTS:"Enable ZTS",hardcodedINI:"Hardcoded INI options",hardcodedINIPlacehoder:"If you need to hardcode ini, write one per line, for example: memory_limit=2G",resultShowDownload:"Download with corresponding extension dependencies",downloadExtOnlyCommand:"Download sources by extensions command",downloadAllCommand:"Download all sources command",downloadUPXCommand:"Download UPX command",compileCommand:"Compile command",downloadPhpVersion:"Download PHP version",downloadSPCBinaryCommand:"Download spc binary command",selectedArch:"Select build architecture",selectedSystem:"Select Build OS",buildLibs:"Select Dependencies",depTips:"After selecting the extensions, the unselectable items are essential dependencies. In the compiled dependencies list, optional dependencies consist of existing extensions and optional dependencies of libraries. Optional dependencies will be added in --with-libs parameter.",microUnavailable:"Micro does not support PHP 7.4 and earlier versions!",windowsSAPIUnavailable:"Windows does not support fpm and embed build!",useUPX:"Enable UPX compression (reduce binary size)",windowsDownSPCWarning:"Please download the binary file manually, extract it to the current directory and rename to spc.exe on Windows!",usePreBuilt:"Download pre-built dependencies if possible (reduce compile time)"}},le=["cli","fpm","micro","embed","all"],te=()=>{h.value=["apcu","bcmath","calendar","ctype","curl","dba","dom","exif","filter","fileinfo","gd","iconv","intl","mbstring","mbregex","mysqli","mysqlnd","openssl","opcache","pcntl","pdo","pdo_mysql","pdo_sqlite","pdo_pgsql","pgsql","phar","posix","readline","redis","session","simplexml","sockets","sodium","sqlite3","tokenizer","xml","xmlreader","xmlwriter","xsl","zip","zlib"]},Q=D(()=>h.value.join(",")),ne=D(()=>{const p=k.value.filter(l=>V.value.indexOf(l)===-1);return p.length>0?' --with-libs="'+p.join(",")+'"':""}),h=w([]),k=w([]),X=w([]),V=w([]),v=w(["cli"]),A=w("spc"),U=w("8.2"),B=w(0),L=w(0),$=w(1),P=w(1),W=w(0),M=w(""),r=w("linux");G(r,()=>{r.value==="windows"&&(T.value="x86_64")});const T=w("x86_64"),j=D(()=>{switch(A.value){case"native":return"bin/spc";case"spc":return r.value==="windows"?".\\spc.exe":"./spc";case"docker":return"bin/spc-alpine-docker";default:return""}}),K=w("--build-cli"),oe=D(()=>{const p=M.value.split(`
`);let l=[];return p.forEach(s=>{s.indexOf("=")>=1&&l.push(s)})," "+l.map(s=>'-I "'+s+'"').join(" ")}),C=w(""),H=(p,l)=>p.includes(C.value)?l===0?p.substring(0,p.indexOf(C.value)):l===1?C.value:p.substring(p.indexOf(C.value)+C.value.length):l===0?p:"",ae=p=>{let l;v.value.indexOf("all")!==-1&&p.target.value==="all"?v.value=["all"]:(l=v.value.indexOf("all"))!==-1&&p.target.value!=="all"&&v.value.splice(l,1),K.value=v.value.map(s=>"--build-"+s).join(" ")},de=p=>{const l=new Set,s=c=>{let u=[];if(r.value==="linux"){if(u=t.value[c]["ext-depends-linux"]??t.value[c]["ext-depends-unix"]??t.value[c]["ext-depends"]??[],u.length===0)return}else if(r.value==="macos"){if(u=t.value[c]["ext-depends-macos"]??t.value[c]["ext-depends-unix"]??t.value[c]["ext-depends"]??[],u.length===0)return}else if(r.value==="windows"&&(u=t.value[c]["ext-depends-windows"]??t.value[c]["ext-depends"]??[],u.length===0))return;u.forEach(b=>{l.add(b),s(b)})};return p.forEach(c=>{s(c)}),Array.from(l)},ue=D(()=>`${j.value} download --all --with-php=${U.value}${P.value?" --prefer-pre-built":""}${B.value?" --debug":""}`),pe=D(()=>`${j.value} download --with-php=${U.value} --for-extensions "${Q.value}"${P.value?" --prefer-pre-built":""}${B.value?" --debug":""}`),re=D(()=>`${j.value} install-pkg upx${B.value?" --debug":""}`),ce=D(()=>`${j.value} build ${K.value} "${Q.value}"${ne.value}${B.value?" --debug":""}${L.value?" --enable-zts":""}${W.value?" --with-upx-pack":""}${oe.value}`),be=p=>{const l=new Set,s=u=>{let b=[];if(r.value==="linux"){if(b=a.value[u]["lib-depends-linux"]??a.value[u]["lib-depends-unix"]??a.value[u]["lib-depends"]??[],b.length===0)return}else if(r.value==="macos"){if(b=a.value[u]["lib-depends-macos"]??a.value[u]["lib-depends-unix"]??a.value[u]["lib-depends"]??[],b.length===0)return}else if(r.value==="windows"&&(b=a.value[u]["lib-depends-windows"]??a.value[u]["lib-depends"]??[],b.length===0))return;b.forEach(q=>{l.add(q),s(q)})},c=u=>{let b=[];if(r.value==="linux"){if(b=t.value[u]["lib-depends-linux"]??t.value[u]["lib-depends-unix"]??t.value[u]["lib-depends"]??[],b.length===0)return}else if(r.value==="macos"){if(b=t.value[u]["lib-depends-macos"]??t.value[u]["lib-depends-unix"]??t.value[u]["lib-depends"]??[],b.length===0)return}else if(r.value==="windows"&&(b=t.value[u]["lib-depends-windows"]??t.value[u]["lib-depends"]??[],b.length===0))return;b.forEach(q=>{l.add(q),s(q)})};return p.forEach(u=>{c(u)}),Array.from(l)};return G(r,()=>h.value=[]),G(r,()=>W.value=0),G(h,p=>{X.value=de(p),X.value.forEach(s=>{h.value.indexOf(s)===-1&&h.value.push(s)}),h.value.sort(),console.log("检测到变化！"),console.log(p);const l=wl({ext:t.value,lib:a.value,os:r.value},h.value);y.value=l.libs.sort(),k.value=[],V.value=be(l.exts),V.value.forEach(s=>{k.value.indexOf(s)===-1&&k.value.push(s)})}),(p,l)=>(g(),x("div",null,[e("h2",null,n(o[i.lang].selectedSystem),1),e("div",yl,[(g(),x(N,null,O(z,(s,c)=>e("span",{key:c,style:{"margin-right":"8px"}},[m(e("input",{type:"radio",id:"os-"+s.os,value:s.os,disabled:s.disabled===!0,"onUpdate:modelValue":l[0]||(l[0]=u=>r.value=u)},null,8,hl),[[f,r.value]]),e("label",{for:"os-"+s.os},n(s.label),9,vl)])),64))]),e("div",fl,[m(e("select",{"onUpdate:modelValue":l[1]||(l[1]=s=>T.value=s)},[zl,e("option",{value:"aarch64",disabled:r.value==="windows"},"aarch64",8,Sl)],512),[[R,T.value]])]),e("h2",null,n(o[i.lang].selectExt)+n(h.value.length>0?" ("+h.value.length+")":""),1),e("div",Dl,[m(e("input",{class:"input","onUpdate:modelValue":l[2]||(l[2]=s=>C.value=s),placeholder:"Highlight search..."},null,512),[[Y,C.value]]),kl,(g(!0),x(N,null,O(d.value,s=>(g(),x("div",Bl,[e("span",null,[m(e("input",{type:"checkbox",id:s,value:s,"onUpdate:modelValue":l[3]||(l[3]=c=>h.value=c),disabled:X.value.indexOf(s)!==-1},null,8,ql),[[F,h.value]]),e("label",{for:s},[e("span",null,n(H(s,0)),1),e("span",Wl,n(H(s,1)),1),e("span",null,n(H(s,2)),1)],8,El)])]))),256))]),r.value!=="windows"?(g(),x("div",{key:0,class:"my-btn",onClick:te},n(o[i.lang].selectCommon),1)):I("",!0),e("div",{class:"my-btn",onClick:l[4]||(l[4]=s=>h.value=[])},n(o[i.lang].selectNone),1),e("details",Cl,[e("summary",null,n(o[i.lang].buildLibs)+n(k.value.length>0?" ("+k.value.length+")":""),1),e("div",Il,[(g(!0),x(N,null,O(y.value,(s,c)=>(g(),x("div",Pl,[m(e("input",{type:"checkbox",id:c,value:s,"onUpdate:modelValue":l[5]||(l[5]=u=>k.value=u),disabled:V.value.indexOf(s)!==-1},null,8,_l),[[F,k.value]]),e("label",{for:c},n(s),9,Ul)]))),256))])]),e("div",Ll,[$l,e("p",null,n(o[i.lang].depTips),1)]),e("h2",null,n(o[i.lang].buildTarget),1),e("div",Nl,[(g(),x(N,null,O(le,s=>e("div",Ol,[m(e("input",{type:"checkbox",id:"build_"+s,value:s,"onUpdate:modelValue":l[6]||(l[6]=c=>v.value=c),onChange:ae},null,40,Vl),[[F,v.value]]),e("label",{for:"build_"+s},n(s),9,Al)])),64))]),U.value==="7.4"&&(v.value.indexOf("micro")!==-1||v.value.indexOf("all")!==-1)?(g(),x("div",Tl,[jl,e("p",null,n(o[i.lang].microUnavailable),1)])):I("",!0),r.value==="windows"&&(v.value.indexOf("fpm")!==-1||v.value.indexOf("embed")!==-1)?(g(),x("div",Gl,[Xl,e("p",null,n(o[i.lang].windowsSAPIUnavailable),1)])):I("",!0),e("h2",null,n(o[i.lang].buildOptions),1),e("table",null,[e("tr",null,[e("td",null,n(o[i.lang].buildEnvironment),1),e("td",null,[m(e("select",{"onUpdate:modelValue":l[7]||(l[7]=s=>A.value=s)},[e("option",Ml,n(o[i.lang].buildEnvNative),1),e("option",Hl,n(o[i.lang].buildEnvSpc),1),r.value!=="windows"?(g(),x("option",Rl,n(o[i.lang].buildEnvDocker),1)):I("",!0)],512),[[R,A.value]])])]),e("tr",null,[e("td",null,n(o[i.lang].downloadPhpVersion),1),e("td",null,[m(e("select",{"onUpdate:modelValue":l[8]||(l[8]=s=>U.value=s)},[(g(),x(N,null,O(Z,s=>e("option",{value:s},n(s),9,Fl)),64))],512),[[R,U.value]])])]),e("tr",null,[e("td",null,n(o[i.lang].useDebug),1),e("td",null,[m(e("input",{type:"radio",id:"debug-yes",value:1,"onUpdate:modelValue":l[9]||(l[9]=s=>B.value=s)},null,512),[[f,B.value]]),e("label",Zl,n(o[i.lang].yes),1),m(e("input",{type:"radio",id:"debug-no",value:0,"onUpdate:modelValue":l[10]||(l[10]=s=>B.value=s)},null,512),[[f,B.value]]),e("label",Ql,n(o[i.lang].no),1)])]),e("tr",null,[e("td",null,n(o[i.lang].useZTS),1),e("td",null,[m(e("input",{type:"radio",id:"zts-yes",value:1,"onUpdate:modelValue":l[11]||(l[11]=s=>L.value=s)},null,512),[[f,L.value]]),e("label",Kl,n(o[i.lang].yes),1),m(e("input",{type:"radio",id:"zts-no",value:0,"onUpdate:modelValue":l[12]||(l[12]=s=>L.value=s)},null,512),[[f,L.value]]),e("label",Yl,n(o[i.lang].no),1)])]),e("tr",null,[e("td",null,n(o[i.lang].resultShowDownload),1),e("td",null,[m(e("input",{type:"radio",id:"show-download-yes",value:1,"onUpdate:modelValue":l[13]||(l[13]=s=>$.value=s)},null,512),[[f,$.value]]),e("label",Jl,n(o[i.lang].yes),1),m(e("input",{type:"radio",id:"show-download-no",value:0,"onUpdate:modelValue":l[14]||(l[14]=s=>$.value=s)},null,512),[[f,$.value]]),e("label",et,n(o[i.lang].no),1)])]),e("tr",null,[e("td",null,n(o[i.lang].usePreBuilt),1),e("td",null,[m(e("input",{type:"radio",id:"pre-built-yes",value:1,"onUpdate:modelValue":l[15]||(l[15]=s=>P.value=s)},null,512),[[f,P.value]]),e("label",it,n(o[i.lang].yes),1),m(e("input",{type:"radio",id:"pre-built-no",value:0,"onUpdate:modelValue":l[16]||(l[16]=s=>P.value=s)},null,512),[[f,P.value]]),e("label",st,n(o[i.lang].no),1)])]),r.value!=="macos"?(g(),x("tr",lt,[e("td",null,n(o[i.lang].useUPX),1),e("td",null,[m(e("input",{type:"radio",id:"upx-yes",value:1,"onUpdate:modelValue":l[17]||(l[17]=s=>W.value=s)},null,512),[[f,W.value]]),e("label",tt,n(o[i.lang].yes),1),m(e("input",{type:"radio",id:"upx-no",value:0,"onUpdate:modelValue":l[18]||(l[18]=s=>W.value=s)},null,512),[[f,W.value]]),e("label",nt,n(o[i.lang].no),1)])])):I("",!0)]),e("h2",null,n(o[i.lang].hardcodedINI),1),m(e("textarea",{class:"textarea",placeholder:o[i.lang].hardcodedINIPlacehoder,"onUpdate:modelValue":l[19]||(l[19]=s=>M.value=s),rows:"5"},null,8,ot),[[Y,M.value]]),e("h2",null,n(o[i.lang].resultShow),1),A.value==="spc"?(g(),x("div",at,[e("b",null,n(o[i.lang].downloadSPCBinaryCommand),1),r.value!=="windows"?(g(),x("div",dt,[ge(" curl -fsSL -o spc.tgz https://dl.static-php.dev/static-php-cli/spc-bin/nightly/spc-"+n(r.value)+"-"+n(T.value)+".tar.gz && tar -zxvf spc.tgz && rm spc.tgz",1),ut])):(g(),x("div",pt,[e("div",rt,[ct,e("p",null,n(o[i.lang].windowsDownSPCWarning),1),bt])]))])):I("",!0),$.value?(g(),x("div",wt,[e("b",null,n(o[i.lang].downloadExtOnlyCommand),1),e("div",gt,n(pe.value),1)])):(g(),x("div",xt,[e("b",null,n(o[i.lang].downloadAllCommand),1),e("div",mt,n(ue.value),1)])),W.value?(g(),x("div",yt,[e("b",null,n(o[i.lang].downloadUPXCommand),1),e("div",ht,n(re.value),1)])):I("",!0),e("div",vt,[e("b",null,n(o[i.lang].compileCommand),1),e("div",ft,n(ce.value),1)])]))}}),kt=ye(St,[["__scopeId","data-v-36cf2ac6"]]);export{kt as C};
