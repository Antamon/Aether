window.addEventListener("load",()=>{

    //Check login status
    fetch('checkLogin.php')
    .then(response => response.json())
    .then(data => {
        console.log(data);
        if (data.status !== 'ok') window.location.href = '../index.html';
        else{
            document.getElementById('idUser').value = data['user']['id'];
            document.getElementById('displayName').value = data['user']['displayName'];
            document.getElementById('navbarName').innerHTML = "Welkom " + data['user']['displayName'];
        }
    })
    .catch(error => {
        console.error('Fout bij sessiecontrole:', error);
        window.location.href = 'login.html';
    });

    // Get character data
    function getCharacter(id){
        let dataObject = {
            id: id
        };
        fetch("api/characters/getCharacter.php", {
            method: 'POST',
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify(dataObject)
        })
        .then(response => response.json())
        .then((data)=>{
            const velden = ["firstName","lastName","class","birthDate","birthPlace","nationality","stateRegisterNumber","street","houseNumber","municipality","postalCode","title","maritalStatus","profession"];
            velden.forEach((veld) =>{
                document.getElementById(veld).value = data[veld];
            });
            document.getElementById("groupNewCharacter").classList.add('d-none');
            document.getElementById("groupEditCharacter").classList.remove('d-none');
            document.getElementById('idCharacter').value = data["id"];
            const accordionSkills = document.getElementById("accordionSkills");
            while (accordionSkills.firstChild) accordionSkills.removeChild(accordionSkills.lastChild);
            data['skills'].forEach((skill)=>{
                accordionSkills.appendChild(addSkillAccordionItem(skill));
            });
            getNewSkills(data["id"]);
        });
    }

    // Generate list of character names in buttons
    function characterList(){
        let dataObject = {};
        fetch("api/characters/getCharacterList.php", {
            method: 'POST',
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify(dataObject)
        })
        .then(response => response.json())
        .then((data)=>{
            const nameList = document.getElementById('characterNames');
            while (nameList.firstChild) nameList.removeChild(nameList.lastChild);
            if(data){
                //<button type="button" class="btn btn-primary btn-sm">Naam 1</button>
                for(const [key,value] of Object.entries(data)){
                    const newButton = document.createElement("button");
                    newButton.classList.add('btn','btn-primary', 'btn-sm', 'm-1');
                    newButton.id = "showCharacter"+value["id"];
                    newButton.innerHTML = value["firstName"] + " " + value["lastName"];
                    newButton.setAttribute('data-idcharacter', value["id"]);
                    newButton.addEventListener('click',function(e){
                        //console.log(e.target);
                        document.getElementById("characterForm").classList.remove('d-none');
                        document.getElementById("skills").classList.remove('d-none');
                        getCharacter(e.target.dataset.idcharacter);
                    })
                    nameList.appendChild(newButton);
                }
            }
        });
    }

    // Get list of skills a character doesn't possess yet.
    function getNewSkills(idCharacter){
        let dataObject = {
            id: idCharacter
        };
        fetch("api/characters/getNewSkills.php", {
            method: 'POST',
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify(dataObject)
        })
        .then(response => response.json())
        .then((data)=>{
            const idNewSkill = document.getElementById("idNewSkill");
            while (idNewSkill.firstChild) idNewSkill.removeChild(idNewSkill.lastChild);
            for(const [key,value] of Object.entries(data)){
                const newOption = document.createElement("option");
                newOption.innerHTML = value["name"].charAt(0).toUpperCase() + value["name"].slice(1);
                newOption.value = value["id"];
                idNewSkill.appendChild(newOption);
            }
        }); 
    }

    // Insert new character in database
    document.getElementById("createNewCharacter").addEventListener('click',()=>{
        
        const velden = ["firstName","lastName","class","birthDate","birthPlace","nationality","stateRegisterNumber","street","houseNumber","municipality","postalCode","title","maritalStatus","profession"];
        let dataObject = {};
        velden.forEach((veld) =>{
            dataObject[veld] = document.getElementById(veld).value;
        });
        fetch("api/characters/newCharacter.php", {
            method: 'POST',
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify(dataObject)
        })
        .then(response => response.text())
        .then((data)=>{
            console.log(data);
            document.getElementById('idCharacter').value = data.id;
            clearCharacterFields();
            document.getElementById("groupNewCharacter").classList.add('d-none');
            characterList();
        });
    });

    // Update existing character
    document.getElementById("updateCharacter").addEventListener('click',(e)=>{
    
        const velden = ["firstName","lastName","class","birthDate","birthPlace","nationality","stateRegisterNumber","street","houseNumber","municipality","postalCode","title","maritalStatus","profession"];
        let dataObject = {}
        velden.forEach((veld) =>{
            dataObject[veld] = document.getElementById(veld).value;
        });
        dataObject.id = document.getElementById('idCharacter').value;
        console.log(dataObject);
        fetch("api/characters/updateCharacter.php", {
            method: 'POST',
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify(dataObject)
        })
        .then(response => response.text())
        .then((data)=>{
            document.getElementById("showCharacter"+dataObject.id).innerHTML=dataObject['firstName'] + " " + dataObject['lastName'];
        });
    });

    // Add new skill
    document.getElementById("addNewSkill").addEventListener('click',(e)=>{
        let dataObject = {
            idCharacter : document.getElementById('idCharacter').value,
            idSkill : document.getElementById('idNewSkill').value,
            level : 0
        }
        fetch("api/characters/AddNewSkill.php", {
            method: 'POST',
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify(dataObject)
        })
        .then(response => response.json())
        .then((data)=>{
            console.log(data);
            let skill = {
                id : data['id'],
                name : data['name'],
                description : data['description'],
                beginner : data['beginner'],
                professional : data['professional'],
                master : data['master'],
                level : 0
            }
            
            const accordionSkills = document.getElementById("accordionSkills");
            accordionSkills.appendChild(addSkillAccordionItem(skill));
        });
    });

    // Create Skill accordion item
    function addSkillAccordionItem(skill){
        let proficiency = "ongetrained";
        if(skill.level == 1) proficiency = "Beginneling";
        if(skill.level == 2) proficiency = "Deskundige";
        if(skill.level == 3) proficiency = "Meester";
        const accordionItem = document.createElement("div");
        accordionItem.classList.add('accordion-item');
        const newH2 = document.createElement("h2");
        newH2.classList.add('accordion-header');
        const newButton = document.createElement("button");
        newButton.classList.add('accordion-button','collapsed');
        newButton.setAttribute("type","button");
        newButton.setAttribute("data-bs-toggle","collapse");
        newButton.setAttribute("data-bs-target","#collapse"+skill.id);
        newButton.setAttribute("aria-expanded","true");
        newButton.setAttribute("aria-controls","collapse"+skill.id);
        newButton.innerHTML = skill.name.charAt(0).toUpperCase() + skill.name.slice(1) + " - " + proficiency ;
        newH2.appendChild(newButton);
        accordionItem.appendChild(newH2);
        const collapse = document.createElement("div");
        collapse.classList.add('accordion-collapse','collapse');
        collapse.setAttribute("id","collapse"+skill.id);
        collapse.setAttribute("data-bs-parent","#accordionSkills");
        const accordionBody = document.createElement("div");
        accordionBody.classList.add('accordion-body');
        let opacityItem = "";
        accordionBody.innerHTML = "<p>"+skill.description+"</p>";
        if(skill.level < 1) opacityItem = "opacity-25";
        accordionBody.innerHTML += "<p class='"+opacityItem+"'>1 - "+skill.beginner+"</p>";
        if(skill.level < 2) opacityItem = "opacity-25";
        accordionBody.innerHTML += "<p class='"+opacityItem+"'>2 - "+skill.professional+"</p>";
        if(skill.level < 3) opacityItem = "opacity-25";
        accordionBody.innerHTML += "<p class='"+opacityItem+"'>3 - "+skill.master+"</p>";
        collapse.appendChild(accordionBody);
        accordionItem.appendChild(collapse);
        return accordionItem;
        //accordionSkills.appendChild(accordionItem);
    }


    // Clear characters fields
    function clearCharacterFields(){
        const velden = ["firstName","lastName","class","birthDate","birthPlace","nationality","stateRegisterNumber","street","houseNumber","municipality","postalCode","title","maritalStatus","profession"];
        velden.forEach((veld) =>{
            document.getElementById(veld).value = "";
        });
    }

    // Cancel insert new character
    document.getElementById("cancelNewCharacter").addEventListener('click',()=>{
        clearCharacterFields();
        document.getElementById("characterForm").classList.add('d-none');        
    });

    // Show new character buttons
    document.getElementById("startNewCharacter").addEventListener('click',()=>{
        clearCharacterFields();
        document.getElementById("characterForm").classList.remove('d-none');
        document.getElementById("groupNewCharacter").classList.remove('d-none');
        document.getElementById("groupEditCharacter").classList.add('d-none');
        document.getElementById("skills").classList.add('d-none');
    });

    // Initialiseer functies bij openen van pagina
    characterList();

});


