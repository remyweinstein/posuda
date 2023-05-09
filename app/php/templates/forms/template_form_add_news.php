<!DOCTYPE html>
<html lang="ru">

<head>
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no, user-scalable=no" />
  <meta name="theme-color" content="#33C3F0"/>

  <link rel="manifest" href="manifest.json" />

  <link rel="icon" type="image/png" sizes="192x192"
    href="app/assets/icons/192x192.png" />
  <link rel="apple-touch-icon" type="image/png" sizes="180x180"
    href="app/assets/icons/180x180.png" />

  <title>Мир посуды: Лояльность</title>
  <script src="app/build/js/ckeditor.js"></script>
  <link rel="stylesheet" href="app/build/styles/google-open-sans.css" />
  <link rel="stylesheet" href="app/build/styles/normalize.css" />
  <link rel="stylesheet" href="app/build/styles/skeleton_new.css" />
  <link rel="stylesheet" href="app/build/styles/style_desktop.css" />
</head>

<body>
    <style>
        body {
            font-family: "Open Sans", sans-serif;
            color: #222;
        }
        input, textarea {
            display: block;
            width:100%;
        }
        label {
            position: relative;
            font-weight: 300;
            top: -4.5rem;
            margin-left: 1rem;
            transition: 0.3s font-size, 0.3s top;
        }
        textarea + label {
            top: -8rem;
        }
        label[for=desc] {
            top: -38.5rem;
            font-size: 1.25rem;
        }
        label[for=date] {
            top: 0rem;
            width: 30%;
        }
        label[for=img] {
            top: 2rem;
        }
        input#img, input#date {
            width: 60%;
            display: inline-block;
            float: right;
        }
        .file {
            margin-bottom: 7rem;
        }
        .activeINPUT {
            top: -7.5rem;
            font-size: 1.25rem;
        }
        .activeTEXTAREA {
            top: -10.5rem;
            font-size: 1.25rem;
        }
        .ck-editor__editable_inline {
            min-height: 300px;
            margin-bottom: 2rem;
        }
    </style>
    <?php
    $data['id']           = 0;
    $data['title']        = "";
    $data['description']  = "";
    $data['date_to_post'] = "";
    $data['image']        = "";
    $data['is_active']    = 1;
    
    if (isset($news) && isset($news['id'])) {
        $data = $news;
     }
    ?>
    <div style="max-width:600px;margin:10rem auto;padding: 3rem;box-shadow: rgb(0 0 0 / 21%) 0px 2px 28px;">
        <form action="" method="POST"  enctype="multipart/form-data">
            <?php if ($data['id'] > 0) { ?>
                <input type="hidden" name="id" value="<?=$data['id']?>" />
            <?php } ?>
            <div>
                <input id="title" type="text" name="title" value="<?=$data['title']?>" required/>
                <label for="title">Название</label>
            </div>
            <div>
                <textarea id="desc" name="desc"><?=$data['description']?></textarea>
            </div>
            <div>
               <input id="date" type="date" name="date" value="<?=$data['date_to_post']?>" required/>
                <label for="date">Дата публикации:</label>
            </div>
            <div class="file">
               <input id="img" type="file" name="img" value="" <?php if ($data['image'] == "") { ?> required <?php } ?>/>
                <label for="img">Картинка:</label>
                <?php if($data['image'] !== ""){ ?>
                    <img style="width:100%" src="<?=$data['image']?>">
                <?php } ?>
            </div>
            <div>
               <input id="is_active" type="checkbox" name="is_active" <?=($data['is_active'])?"checked":null?> style="top:-10px;position:relative" />
                <label for="is_active">Показывать на сайте</label>
            </div>
            <div>
               <input id="key" type="text" name="key" value="" required/>
                <label for="key">Ключ</label>
            </div>
            <button type="submit" style="margin-top:5rem;">Отправить</button>
            <a href="/list-news" style="margin-top:5rem;float:right" class="button">Список новостей</a>
        </form>
    </div>
    <script>
        ClassicEditor
            .create(document.querySelector('#desc'))
            .catch(error => {
                console.error(error);
            });
    </script>
    <script>
        let d = document;
        d.querySelectorAll("input[type=text]").forEach((el) => {
            let clas  = "activeINPUT",
                label = d.querySelector("[for=" + el.id + "]").classList,
                exc   = ["date", "img"];
            
            if (el.value) label.add(clas);
            
            if (!exc.includes(el.id)) {
                el.addEventListener("focus", () => label.add(clas));
                el.addEventListener("blur",  () => {
                    if (!el.value) label.remove(clas);
                });
            }
        });
    </script>
</body>
</html>