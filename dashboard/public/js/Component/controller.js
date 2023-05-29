let gen = (e) => {

    let os = document.querySelector('select[name="os"]')
    let with_docker = document.querySelector('select[name="without-docker"]')
    let compiler = document.querySelector('select[name="compiler"]')
    let with_http_proxy = document.querySelector('input[name="with-http-proxy"]')
    let with_debug = document.querySelector('select[name="with-debug"]')

    let cmd = "./bin/spc"


    cmd += " " + compiler.value + " "


    /*
    if (os.value === 'macos') {
        cmd += " @os=" + os.value
    }

    if (with_docker.value === "1") {
        cmd += "  --without-docker=" + with_docker.value
    } else {
        if (os.value === 'macos') {
            cmd += "  --without-docker=1"
        }
    }

    if (with_http_proxy.value.length > 0) {
        cmd += "  --with-http-proxy=" + with_http_proxy.value
    }

    */

    if (with_debug.value.length > 0) {
        cmd += "  " + with_debug.value
    }

    let libs = ''
    let dpend_libs_obj = document.querySelectorAll('#depend_libraries li input[checked]')
    if (dpend_libs_obj.length > 0) {
        dpend_libs_obj.forEach((value) => {
            libs += value.value + ','
        })

    }

    let suggest_depend_libs_obj = document.querySelectorAll('#suggest_depend_libraries li input')
    if (suggest_depend_libs_obj.length > 0) {
        suggest_depend_libs_obj.forEach((value) => {
            if (value.checked) {
                libs += value.value + ','
            }
        })

    }
    let new_cmd = '';
    if (libs.length > 0) {
        libs = libs.substring(0, libs.length - 1)
        new_cmd = cmd + " build:libs \"" + libs + "\" \n\n"
    }
    cmd = new_cmd + cmd + "  build ";


    let extenion_list_obj = document.querySelectorAll('#all_extentions input[type=checkbox]')
    if (extenion_list_obj.length > 0) {
        let extension_list_str = '';
        extenion_list_obj.forEach((value, key, parent) => {
            if (value.checked === true) {
                extension_list_str += value.value + ','
            }

        })
        extension_list_str = extension_list_str.substring(0, extension_list_str.length - 1);
        cmd += " \"" + extension_list_str + "\" "

    }

    let codeBox = document.querySelector('.preview-code .pre-code.preprocessor')
    codeBox.innerText = cmd;
    document.querySelector('.exec-button').setAttribute('data-cmd', cmd)

}

let bindEvent = () => {
    let option_list = document.querySelector('.options-list')
    if (option_list) {
        option_list.addEventListener('click', (event) => {
            if (event.target.nodeName === 'SELECT') {
                console.log(event.target)
                gen()
            } else {
                event.stopPropagation()
                event.preventDefault()
            }

        })
    }

    document.querySelector('input[name="with-http-proxy"]').onchange = (event) => {
        gen()
    }
    document.querySelector('#suggest_depend_libraries').onclick = (event) => {
        if (event.target.nodeName === 'INPUT') {
            console.log(event.target)
            gen()
        } else {
            event.stopPropagation()
            //event.preventDefault()
        }
    }

    document.querySelector('.generate-cmd-button').addEventListener('click', (e) => {
        gen()
    })

    document.querySelector('.exec-button').addEventListener('click', (event) => {
        let cmd = event.target.getAttribute('data-cmd');
        console.log(cmd)
        let message = {
            "action": "preprocessor",
            "data": cmd
        }
        JSON.stringify(message)
    })

}

let show_controller = () => {
    bindEvent()
    gen()
}
export {show_controller}
