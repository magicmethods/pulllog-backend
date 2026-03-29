# 不具合報告
1. アップロード済みの資産に対して個別に資産データを取得する `GET /gallery/assets/{id}` のレスポンスが 403 のHTMLになり（JSONレスポンスでもない）新たな署名付きURLを得ることができない。下記、実行ログ:
```bash
curl -X GET -H "x-api-key:v1:73bbc05f-8685-4750-a0a3-c306df6c1d07" -H "x-csrf-token:15038633b6520eb4a86728e02b843304da4c17084e2e8e489d6ff156ed5a5727" -H "Content-Type: application/json" http://localhost:3030/api/v1/gallery/assets
{
    "data":[
        {
            "id":"2d6aeaa2-2d3b-461e-8118-43b295a3450f",
            "userId":3,
            "appId":null,
            "appKey":null,
            "appName":null,
            "logId":null,
            "disk":"public",
            "path":"gallery\/2025\/10\/img_68e8bf5df37c36.65572076.jpg",
            "url":"http:\/\/localhost:3030\/api\/v1\/gallery\/assets\/2d6aeaa2-2d3b-461e-8118-43b295a3450f\/content?expires=1760084386&user=3&variant=original&signature=1689238ec5073343004fedafbc3e508f4e3320bf89614cb135a7d4d0f143f0a5",
            "thumbSmall":"gallery\/2025\/10\/thumbs\/s_68e8bf5e2f4698.01504942.jpg",
            "thumbSmallUrl":"http:\/\/localhost:3030\/api\/v1\/gallery\/assets\/2d6aeaa2-2d3b-461e-8118-43b295a3450f\/content?expires=1760084386&user=3&variant=small&signature=9815e64fbfe8870665c3c87fb8da68665f6b5b98ddd51a01f3886c247fbc4f38",
            "thumbLarge":"gallery\/2025\/10\/thumbs\/l_68e8bf5e7cfea8.72969212.jpg",
            "thumbLargeUrl":"http:\/\/localhost:3030\/api\/v1\/gallery\/assets\/2d6aeaa2-2d3b-461e-8118-43b295a3450f\/content?expires=1760084386&user=3&variant=large&signature=568a5e4f82ada9fef63192dfd177f9be71278764311eccc6e25446a1d9688df3",
            "mime":"image\/jpeg",
            "bytes":101597,
            "bytesThumbSmall":15320,
            "bytesThumbLarge":158839,
            "width":1280,
            "height":854,
            "hashSha256":"8965192866327bc5da99c28f68385ae305915d1366fdf0b73e70167190121b11",
            "title":null,
            "description":null,
            "tags":[],
            "visibility":"private",
            "createdAt":"2025-10-10T08:10:06+09:00",
            "updatedAt":"2025-10-10T08:10:06+09:00",
            "deletedAt":null
        }
    ],
    "links":{
        "first":"http:\/\/localhost:3030\/api\/v1\/gallery\/assets?page=1",
        "last":"http:\/\/localhost:3030\/api\/v1\/gallery\/assets?page=1",
        "prev":null,
        "next":null
    },
    "meta":{
        "current_page":1,
        "from":1,
        "last_page":1,
        "links":[
            {
                "url":null,
                "label":"&laquo; Previous",
                "active":false
            },
            {
                "url":"http:\/\/localhost:3030\/api\/v1\/gallery\/assets?page=1",
                "label":"1",
                "active":true
            },
            {
                "url":null,
                "label":"Next &raquo;",
                "active":false
            }
        ],
        "path":"http:\/\/localhost:3030\/api\/v1\/gallery\/assets",
        "per_page":30,
        "to":1,
        "total":1
    }
}
curl -X GET -H "x-api-key:v1:73bbc05f-8685-4750-a0a3-c306df6c1d07" -H "x-csrf-token:15038633b6520eb4a86728e02b843304da4c17084e2e8e489d6ff156ed5a5727" -H "Content-Type: application/json" http://localhost:3030/api/v1/gallery/assets/2d6aeaa2-2d3b-461e-8118-43b295a3450f
{"data":{"id":"2d6aeaa2-2d3b-461e-8118-43b295a3450f","userId":3,"appId":null,"appKey":null,"appName":null,"logId":null,"disk":"public","path":"gallery\/2025\/10\/img_68e8bf5df37c36.65572076.jpg","url":"http:\/\/localhost:3030\/api\/v1\/gallery\/assets\/2d6aeaa2-2d3b-461e-8118-43b295a3450f\/content?expires=1760087284&user=3&variant=original&signature=7e9692b35f8a009dab64751d88907f5fbe81ac25da050f1599a78f12559cc7c1","thumbSmall":"gallery\/2025\/10\/thumbs\/s_68e8bf5e2f4698.01504942.jpg","thumbSmallUrl":"http:\/\/localhost:3030\/api\/v1\/gallery\/assets\/2d6aeaa2-2d3b-461e-8118-43b295a3450f\/content?expires=1760087284&user=3&variant=small&signature=0dca7a2e0b5a735c0b11b4a40afbe2cf28744681cbad2a558bb59ca0ec8f05d7","thumbLarge":"gallery\/2025\/10\/thumbs\/l_68e8bf5e7cfea8.72969212.jpg","thumbLargeUrl":"http:\/\/localhost:3030\/api\/v1\/gallery\/assets\/2d6aeaa2-2d3b-461e-8118-43b295a3450f\/content?expires=1760087284&user=3&variant=large&signature=7f60e5fd19cf94a5e6cbbe58b0faa23bed1cc1bf0452c5e1532a6b439cd40dc0","mime":"image\/jpeg","bytes":101597,"bytesThumbSmall":15320,"bytesThumbLarge":158839,"width":1280,"height":854,"hashSha256":"8965192866327bc5da99c28f68385ae305915d1366fdf0b73e70167190121b11","title":null,"description":null,"tags":[],"visibility":"private","createdAt":"2025-10-10T08:10:06+09:00","updatedAt":"2025-10-10T08:10:06+09:00","deletedAt":null}}
```
→ 修正された。Ok

2. 署名なしパスを叩くと 403 になるが、クエリパラメータが解決できない旨の通知or警告？が含まれるのは問題ないか
→ URL部をパースされないようにクォートで括ったところOk
```bash
curl -I "http://localhost:3030/api/v1/gallery/assets/2d6aeaa2-2d3b-461e-8118-43b295a3450f/content?expires=1760084944&user=3&variant=original&signature=abcdefg"
HTTP/1.1 403 Forbidden
Host: localhost:3030
Connection: close
X-Powered-By: PHP/8.4.2
Cache-Control: no-cache, private
date: Fri, 10 Oct 2025 08:49:55 GMT
Content-Type: text/html; charset=UTF-8
Vary: Origin
```

3. 署名なしの /storage 経路は 403 / 404 になるはず → 署名なしの /storage 経路のURLはどのようなURLか？
下記アップロード先のURLを直接指定すると 200 レスポンスになるが、これは仕方ない？ もしくは .htaccess 等で制御する？
```bash
curl -I http://localhost:3030/storage/gallery/2025/10/img_68e8bf5df37c36.65572076.jpg
HTTP/1.1 200 OK
Host: localhost:3030
Date: Fri, 10 Oct 2025 08:55:43 GMT
Connection: close
Content-Type: image/jpeg
Content-Length: 101597
```
