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
        input, textarea, select {
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
        #listPhones {
            max-height: 0px;
            transition: 0.5s max-height;
            overflow: hidden;
        }
        #listPhones.active {
            max-height: 300px;
        }
        .ck-editor__editable_inline {
            min-height: 300px;
            margin-bottom: 2rem;
        }
    </style>
    <div style="max-width:600px;margin:10rem auto;padding:3rem;box-shadow:rgb(0 0 0 / 21%) 0px 2px 28px">
        <form action="" method="POST"  enctype="multipart/form-data">
            <div>
                <select name="type" style="margin-bottom:4rem">
                    <option value="phones">По номеру телефона</option>
                    <option value="pushes">Всем клиентам</option>
                </select>
            </div>
            <div id="listPhones" class="active">
                <textarea id="desc" name="phones" placeholder="Список телефонов в один столбец" style="margin-bottom:4rem"></textarea>
            </div>
            <div>
                <input id="title" type="text" name="title" value=""/>
                <label for="title">Заголовок</label>
            </div>
            <div>
                <textarea id="desc" name="desc" placeholder="Текст" style="margin-bottom:4rem"></textarea>
            </div>
            <div>
               <input id="key" type="text" name="key" value="" required/>
                <label for="key">Ключ</label>
            </div>
            <button type="submit" style="margin-top:5rem">Отправить</button>
        </form>
    </div>
    <script>
        let d = document;
        d.querySelectorAll(['input[type="text"]']).forEach((el) => {
            let clas  = "activeINPUT",
                label = d.querySelector(`[for=${el.id}]`).classList,
                exc   = ['date', 'img'];
            
            if (el.value) label.add(clas);
            
            if (!exc.includes(el.id)) {
                el.addEventListener('focus', () => label.add(clas));
                el.addEventListener('blur',  () => {
                    if (!el.value) label.remove(clas);
                });
            }
        });
        d.querySelector('select[name="type"]').addEventListener('change', (e) => {
            const el = e.target;
            let listPhones = d.querySelector('#listPhones');
            
            if (el.value==='phones') {
                listPhones.classList.add('active');
            } else {
                listPhones.classList.remove('active');
            }
        });
    </script>
</body>
</html>