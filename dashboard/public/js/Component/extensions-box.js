let get_content = async (url) => {
    let response = await fetch(url, {
        credentials: 'include',
        method: 'GET',
        mode: 'cors',
        headers: {
            "Access-Control-Request-Method": "GET",
            "Access-Control-Request-Credentials": true,
            "Access-Control-Request-Private-Network": true
        },
    })
    return await response.json();
}

let show_all_extension_list_template = (ext) => {
    let children = '  '
    for (let ext_name in ext) {
        let ext_info = JSON.stringify(ext[ext_name]);
        children += `
                <li data-value="${ext_name}">
                <label  for="ext_${ext_name}" >
                 <input type="checkbox" id="ext_${ext_name}" value="${ext_name}" data-ext-info='${ext_info}' />
                 ${ext_name}
                </label>
                </li>
                `
    }
    document.querySelector('#all_extentions').innerHTML = children;

    document.querySelector('#all_extentions').setAttribute(
        'data-ext',
        JSON.stringify(ext)
    )
}

let show_extension_list = async () => {
    let ext = await get_content('/data/ext.json')
    show_all_extension_list_template(ext)
}
let show_lib = (ext_info) => {
    console.log(ext_info)
    if (ext_info['lib-depends']) {
        ext_info['lib-depends'].map((value, key) => {
            let li = document.createElement('li')
            let ext_name = event.target.value
            let lib_name = value;
            li.setAttribute('data-lib-name', value)
            let box = `
                            <label  >
                             <input type="checkbox" id="lib_${lib_name}" data-ext-name="${ext_name}" value="${lib_name}" checked disabled  />
                             ${lib_name}
                            </label>
                            `
            li.innerHTML = box;

            let ele = document.querySelector(`#lib_${lib_name}`)
            if (!document.querySelector(`#lib_${lib_name}`)) {
                document.querySelector('#depend_libraries').appendChild(li);
            }

        })
    }
    if (ext_info['lib-suggests']) {
        ext_info['lib-suggests'].map((value, key) => {
            let li = document.createElement('li')
            let ext_name = event.target.value
            let lib_name = value;
            li.setAttribute('data-lib-name', value)
            let box = `
                            <label   >
                             <input type="checkbox" id="lib_${lib_name}" data-ext-name="${ext_name}" value="${lib_name}"  />
                             ${lib_name}
                            </label>
                            `
            li.innerHTML = box;
            let ele = document.querySelector(`#lib_${lib_name}`)
            if (!document.querySelector(`#lib_${lib_name}`)) {
                document.querySelector('#suggest_depend_libraries').appendChild(li);
            }
        })
    }
}

let del_lib = (ext_info) => {
    console.log(ext_info)
    if (ext_info['lib-depends']) {
        ext_info['lib-depends'].map((value, key) => {
            let lib_name = value;
            let ele = document.querySelector(`#lib_${lib_name}`)
            if (document.querySelector(`#lib_${lib_name}`)) {
                //console.log(ele.parentNode.parentNode, ele.parentNode.parentNode.parentNode)
                let parentNode = ele.parentNode.parentNode.parentNode
                parentNode.removeChild(ele.parentNode.parentNode)
            }

        })
    }
    if (ext_info['lib-suggests']) {
        ext_info['lib-suggests'].map((value, key) => {
            let lib_name = value;
            let ele = document.querySelector(`#lib_${lib_name}`)
            if (document.querySelector(`#lib_${lib_name}`)) {
                //console.log(ele.parentNode.parentNode, ele.parentNode.parentNode.parentNode)
                let parentNode = ele.parentNode.parentNode.parentNode
                parentNode.removeChild(ele.parentNode.parentNode)
            }
        })
    }
}
let inputCheckBoxBindEvent = () => {

    document.querySelector('#all_extentions').addEventListener('click', (event) => {
        if (event.target.nodeName === 'INPUT') {
            let checked = event.target.checked;
            document.querySelector('.generate-cmd-button').click()
            let ext = event.target.getAttribute('data-ext-info')
            let ext_info = JSON.parse(ext);
            if (!checked) {
                del_lib(ext_info)
            } else {
                show_lib(ext_info)
            }
        }
        event.stopPropagation();
        //event.preventDefault();
    })

    let reset_button = document.querySelector('.reset-cmd-button')
    reset_button.addEventListener('click', (event) => {

        let ext = JSON.parse(
            document.querySelector('#all_extentions')
                .getAttribute('data-ext')
        )
        let button = event.target;
        let checked = true;
        if (button.getAttribute('data-status') === 'enable') {
            button.setAttribute('data-status', "disable")
            button.innerText = '启用所用扩展'
            checked = false;
        } else {
            button.setAttribute('data-status', "enable")
            button.innerText = '停用所有扩展'
            checked = true;
        }
        for (let ext_name in ext) {
            let element = document.querySelector(`#ext_${ext_name}`)
            element.checked = checked;
            if (checked) {
                show_lib(ext[ext_name])
            } else {
                del_lib(ext[ext_name])
            }

        }

        event.stopPropagation();
        event.preventDefault();
        document.querySelector('.generate-cmd-button').click()
    })
}


let extension_list = () => {
    show_extension_list();
    inputCheckBoxBindEvent();
}

export {extension_list}
