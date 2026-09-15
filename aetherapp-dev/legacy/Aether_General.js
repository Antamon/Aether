  //RUN PHP: PAGELOAD
  
	$(document).ready(function(){
        document.getElementById("divCharacter").style.display = 'none';
    });
    
function SwitchCSS(){
    document.getElementById("css").setAttribute("href", "ACS_player_monotype.css");
}

function loadCharacter(){
    //put userID (=code) in session storage
    //Load data in fields
    //make form visible and login hide
    $.ajax({
                type: 'POST',
                url: 'ACS_player_load.php',
                data: {UserId:$('#txtCode').val(), Character:$('#txtRecord').val()},
                success: function(data){$("#divHidden").html(data); 
                popCharacter();
                },
                error: function(){$("#loginMsg").html("<span class='txtLogin'>Foutje gebeurd</span>");}
        });
}

function loadingCharacter(i){
    //var UserId = document.getElementById("CharId"+i).value;
    $.ajax({
                type: 'POST',
                url: 'ACS_player_load.php',
                data: {UserId:$('#CharId'+i).val(), Character:$('#CharRecord'+i).val()},
                success: function(data){$("#divHidden").html(data);
                document.getElementById("txtCode").value = document.getElementById("CharId"+i).value;
                document.getElementById("txtRecord").value = document.getElementById("CharRecord"+i).value;
                popCharacter();
                },
                error: function(){$("#loginMsg").html("<span class='txtLogin'>Character load error</span>");}
        });
}

function loadingPlayer(){
    $.ajax({
                type: 'POST',
                url: 'http://www.digitalthomas.net/Oneiros/aether/ACS_player_listCharacter.php',
                data: {PlayerFirstName:$('#txtPlayerFirstName').val(), PlayerLastName:$('#txtPlayerLastName').val()},
                success: function(data){$("#listCharacterContent").html(data); 
                //listCharacter();
                },
                error: function(){$("#loginMsg").html("<span class='txtLogin'>Player load error</span>");}
        });
}

function loginPlayer(){
    $.ajax({
                type: 'POST',
                url: 'http://www.oneiros.be/aether/Player Admin Tool/PHP/Aether_Login.php',
                data: {username:$('#txtPlayerFirstName').val(), password:$('#txtPlayerLastName').val()},
                success: function(data){$("#listCharacterContent").html(data); 
                //listCharacter();
                },
                error: function(){$("#loginMsg").html("<span class='txtLogin'>Player login error</span>");}
        });
}

function listCharacter(){
    //generates an overview of characters of the applicable ACS_player_load
    
}

function popCharacter(){
        
        document.getElementById("loadMsg").value = "Loading |";
        
        var CharId = document.getElementById("txtCode").value;
        document.getElementById("imgGeneral").src = "http://digitalthomas.net/Oneiros/aether/img/"+CharId+".jpg";
        
        var convClass = document.getElementById("hid-class").value;
        if (convClass == 4){document.getElementById("txtClass").innerHTML = "Bovenklasse";}
        if (convClass == 2 || convClass == 3){document.getElementById("txtClass").innerHTML = "Midden Klasse";}
        if (convClass == 1){document.getElementById("txtClass").innerHTML = "Onderklasse";}
        
        var convGender = document.getElementById("hid-gender").value;
        if (convGender == "male"){document.getElementById("txtGender").innerHTML = "Man";}
        if (convGender == "female"){document.getElementById("txtGender").innerHTML = "Vrouw";}
        
        var convSocStatus = document.getElementById("hid-socialstatus").value;
        if (convSocStatus == "married"){document.getElementById("txtSocialStatus").innerHTML = "Gehuwd";}
        if (convSocStatus == "related"){document.getElementById("txtSocialStatus").innerHTML = "Samenwonend";}
        if (convSocStatus == "single"){document.getElementById("txtSocialStatus").innerHTML = "Ongehuwd";}
        if (convSocStatus == "widow" && convGender == "male"){document.getElementById("txtSocialStatus").innerHTML = "Weduwenaar";}
        if (convSocStatus == "widow" && convGender == "female"){document.getElementById("txtSocialStatus").innerHTML = "Weduwe";}
    
	    document.getElementById("txtLname").innerHTML = document.getElementById("hid-lname").value;
	    document.getElementById("txtFname").innerHTML = document.getElementById("hid-fname").value;
	    document.getElementById("txtBirthPlace").innerHTML = document.getElementById("hid-birthplace").value;
	    document.getElementById("txtBirthDate").innerHTML = document.getElementById("hid-birthdate").value;
	    document.getElementById("txtNationality").innerHTML = document.getElementById("hid-nationality").value;
	    document.getElementById("txtRegNumber").innerHTML = document.getElementById("hid-regnumber").value;
	    document.getElementById("txtStreet").innerHTML = document.getElementById("hid-street").value;
	    document.getElementById("txtHouseNumber").innerHTML = document.getElementById("hid-housenumber").value;
	    document.getElementById("txtPostalCode").innerHTML = document.getElementById("hid-postalcode").value;
	    document.getElementById("txtCity").innerHTML = document.getElementById("hid-city").value;
	    document.getElementById("txtTitle").innerHTML = document.getElementById("hid-title").value;
	    document.getElementById("txtProfession").innerHTML = document.getElementById("hid-profession").value;
	    document.getElementById("txtPostalCode").innerHTML = document.getElementById("hid-postalcode").value;
	    document.getElementById("txtCity").innerHTML = document.getElementById("hid-city").value;
	    document.getElementById("txtTitle").innerHTML = document.getElementById("hid-title").value;
	            
	       //XP calculations
	       //   XpUsed = XpSpend + XpConv   :::     XpMax = XpBase + XpBonus    :::     XpTotal = XpMax - XpUsed
	       //   XpBonus is calculated on number of events participated
	    var XpUsed = Number(document.getElementById("hid-spendXP").value) + Number(document.getElementById("hid-convXP").value);
	    var XpMax = Number(document.getElementById("hid-baseXP").value) + Number(document.getElementById("hid-bonusXP").value);
	    var XpTotal = XpMax - XpUsed;
	    
	    document.getElementById("txtTotalXP").innerHTML = XpTotal;
	    document.getElementById("txtMaxXP").innerHTML = XpMax;
	    document.getElementById("txtConvXP").innerHTML = document.getElementById("hid-convXP").value;
	    document.getElementById("txtSpendXP").innerHTML = document.getElementById("hid-spendXP").value;
	    
	       //SP calculations
	       //   SpMax = SpBase + SpBonus    :::     SpTotal = SpMax - SpSpend
	       //   SpBonus = SP received from XP convertion  (= 2 * XpConv)
	    var SpBonus = 2 * Number(document.getElementById("hid-convXP").value);    
	    var SpMax = Number(document.getElementById("hid-baseSP").value) + SpBonus;
	    var SpTotal = SpMax - Number(document.getElementById("hid-spendSP").value);
	       
	    document.getElementById("txtTotalSP").innerHTML = SpTotal;
	    document.getElementById("txtMaxSP").innerHTML = SpMax;
	       
	    document.getElementById("txtPrestigeTotal").innerHTML = document.getElementById("hid-prestigetotal").value;
	    //document.getElementById("txtPrestigeMax").value = document.getElementById("hid-prestigemax").value + document.getElementById("hid-prestigebonus").value;
	    
	    document.getElementById("txtFinSalary").value = document.getElementById("hid-salary").value;
	    document.getElementById("txtFinSalaryEmp").value = document.getElementById("hid-salaryemp").value;
	    document.getElementById("txtFinEndowment").value = document.getElementById("hid-endowment").value;
	    document.getElementById("txtFinRevenue").value = document.getElementById("hid-revenue").value;
	    document.getElementById("txtFinSavings").value = document.getElementById("hid-savings").value;
	    
	    var charTitle = document.getElementById("hid-title").value;
	    if (charTitle != "" && charTitle != "geen"){
	        document.getElementById("txtProfession").style.display = "none";
	        document.getElementById("spanProfession").style.display = "none";
	    }
	    
	    if (charTitle == "" || charTitle == "geen"){
	        document.getElementById("txtTitle").style.display = "none";
	        document.getElementById("spanTitle").style.display = "none";
	    }
	    
	    //This jQuery code allows to fetch data from another page (unloaded) in the same domain.
	    /*$.get('http://www.digitalthomas.net/Oneiros/aether/ACS_master.html', function(result){
	        var mydata = $(result).find('#lblIDheader').html();
	        $('#txtLname').html(mydata);
	    });*/
	    
	    loadPartner();
	    
	}

function loadLanguage(){            //FETCH LANGUAGE DATA FROM DATABASE
            $.ajax({
                type: 'POST',
                url: 'ACS_player_loadlanguage.php',
                data: {UserId:$('#txtCode').val()},		        
                success: function(data){$("#divLanguageContent").html(data); loadLicence(); document.getElementById("loadMsg").value = "Loading |||";}
            });
    }

function loadEvent(){            //FETCH EVENT DATA FROM DATABASE
	    var baseXP = document.getElementById("txtTotalXP").value;
            $.ajax({
                type: 'POST',
                url: 'ACS_player_loadevent.php',
                data: {UserId:$('#txtCode').val()},		        
                success: function(data){$("#divEventContent").html(data); loadEmployer(); document.getElementById("loadMsg").value = "Loading |||||";}
            });
    }

function loadLicence(){            //FETCH LICENCE DATA FROM DATABASE
            $.ajax({
                type: 'POST',
                url: 'ACS_player_loadlicence.php',
                data: {UserId:$('#txtCode').val()},		        
                success: function(data){$("#divLicenceContent").html(data); loadEvent(); document.getElementById("loadMsg").value = "Loading ||||";}
            });
    }
    
function hideEmptySkills(){
    /*for(i = 0; i < 30; i++){
        if (document.getElementById("txt_sLevel" + i).value = 0){
            document.getElementById("div_skill" + i).style.display = 'none';
            }
        }*/
        if (document.getElementById("txt_sName2").value = ""){
            document.getElementById("div_skill2").style.display = 'none';
            }
    }


function loadTraits(){            //FETCH SKILL DATA FROM DATABASE
            $.ajax({
                type: 'POST',
                url: 'ACS_player_loadtraits.php',
                data: {UserId:$('#txtCode').val()},		        
                success: function(data){$("#divTraitsContent").html(data); loadSkill(); document.getElementById("loadMsg").value = "Loading ||||||||";}
            });
        
        
    }

function loadSkill(){            //FETCH TRAITS DATA FROM DATABASE
            $.ajax({
                type: 'POST',
                url: 'ACS_player_loadskills.php',
                data: {UserId:$('#txtCode').val()},		        
                success: function(data){$("#divSkillsContent").html(data);
                document.getElementById("txtGreen").innerHTML = Number(document.getElementById("txt_countGreen").value);
                document.getElementById("txtYellow").innerHTML = Number(document.getElementById("txt_countYellow").value);
                document.getElementById("txtRed").innerHTML = Number(document.getElementById("txt_countRed").value);
                document.getElementById("txtWhite").innerHTML = Number(document.getElementById("txt_countWhite").value) + Number(document.getElementById("hid-whiterings").value);
                loadBground(); document.getElementById("loadMsg").value = "Loading |||||||||";
                }
            });
    }

function loadEmployer(){            //FETCH EMPLOYEE DATA FROM DATABASE
            $.ajax({
                type: 'POST',
                url: 'ACS_player_loademployer.php',
                data: {UserId:$('#txtCode').val()},		        
                success: function(data){$("#divEmployer").html(data); loadEmployee(); document.getElementById("loadMsg").value = "Loading ||||||";}
            });
    }
    
function loadEmployee(){            //FETCH EMPLOYEE DATA FROM DATABASE
            $.ajax({
                type: 'POST',
                url: 'ACS_player_loademployee.php',
                data: {UserId:$('#txtCode').val()},		        
                success: function(data){$("#divEmployee").html(data); loadTraits(); document.getElementById("loadMsg").value = "Loading |||||||";}
            });
    }
    
function loadPartner(){            //FETCH PARTNER DATA FROM DATABASE
	        $.ajax({
                type: 'POST',
                url: 'ACS_player_loadpartner.php',
                data: {UserId:$('#txtCode').val()},		        
                success: function(data){$("#divPartner").html(data); loadLanguage(); document.getElementById("loadMsg").value = "Loading ||";}
            });
    }
    
function loadBground(){            //FETCH BACKGROUND DATA FROM DATABASE
            $.ajax({
                type: 'POST',
                url: 'ACS_player_loadbground.php',
                data: {UserId:$('#txtCode').val()},		        
                success: function(data){$("#divBgroundContent").html(data); popStats(); document.getElementById("loadMsg").value = "";}
            });
    }
    
function popStats(){
    document.getElementById("txtPrestigeMax").innerHTML = Number(document.getElementById("txt_traitsPrestige").value) + Number(document.getElementById("hid-prestigebonus").value);
    document.getElementById("loadMsg").value = "";
    viewCharacter();
}

function viewCharacter(){
	    document.getElementById("divCharacter").style.display = 'block';
	    document.getElementById("divLogin").style.display = 'block';
}

function btnSubmit(){
    var Char = document.getElementById("txtRecord").value;
    var Code = document.getElementById("txtCode").value;
    
    //if (Char == "" || Code == ""){
    //    document.getElementById("loginMsg").innerHTML = "<span class='txtLogin'>LeegVeld</span>"
    //}
    if (Char == "*" || Code == "*"){
        document.getElementById("loginMsg").innerHTML = "<span class='txtLogin'>Dossier niet gevonden</span>"
    }
    
    if (Char != "" && Code != "" && Char != "*" && Code != "*") {
	    loadCharacter();
    }
}

function btnLoadPlayer(){
    var PlayerFName = document.getElementById("txtPlayerFirstName").value;
    var PlayerLName = document.getElementById("txtPlayerLastName").value;
    
    //if (Char == "" || Code == ""){
    //    document.getElementById("loginMsg").innerHTML = "<span class='txtLogin'>LeegVeld</span>"
    //}
    if (PlayerFName == "*" || PlayerLName == "*"){
        document.getElementById("loginMsg").innerHTML = "<span class='txtLogin'>Dossier niet gevonden</span>"
    }
    
    if (PlayerFName != "" && PlayerLName != "" && PlayerFName != "*" && PlayerLName != "*") {
	    loadingPlayer();
    }
}
	
function closeFile(){
	    $("input[type=text]").val('');
	    $("input[type=hidden]").val('');
	    document.getElementById("divCharacter").style.display = 'none';
	}
	
function characterEdit(){
    localStorage.clear();
    
    var pfn = document.getElementById('txtPlayerFirstName').value;
    var pln = document.getElementById('txtPlayerLastName').value;
    var cfn = document.getElementById('txtFname').innerHTML;
    var cln = document.getElementById('txtLname').innerHTML;
    var ccode = document.getElementById('txtCode').value;
    var crec = document.getElementById('txtRecord').value;
    
    localStorage.setItem("playerfname", pfn);
    localStorage.setItem("playerlname", pln);
    localStorage.setItem("charfname", cfn);
    localStorage.setItem("charlname", cln);
    localStorage.setItem("charcode", ccode);
    localStorage.setItem("charrecord", crec);
    
    var win = window.open("http://www.digitalthomas.net/Oneiros/aether/Aether_Character_Edit.html", "_blank");
    win.focus();
}

//-----------------------------------------------------------------
//   PART 6 : CREATE PASSPORT 
//-----------------------------------------------------------------
//TRANSFER: STORE DATA
    function StoreData(){
        
        localStorage.clear();
        
        var UserId = document.getElementById('txtCode').value;
        var Fname = document.getElementById('txtFname').value;
        var Lname = document.getElementById('txtLname').value;
        var Gender = document.getElementById('txtGender').value;
        var BirthPlace = document.getElementById('txtBirthPlace').value;
        var BirthDate = document.getElementById('txtBirthDate').value;
        var Nationality = document.getElementById('txtNationality').value;
        var RegNumber = document.getElementById('txtRegNumber').value;
        var Street = document.getElementById('txtStreet').value;
        var HouseNumber = document.getElementById('txtHouseNumber').value;
        var PostalCode = document.getElementById('txtPostalCode').value;
        var City = document.getElementById('txtCity').value;
        //var Title = document.getElementById('txtTitle').value;
        var Profession = document.getElementById('txtProfession').value;
        var SocialStatus = document.getElementById('txtSocialStatus').value;
        var PrestigeSaldo = document.getElementById('txtPrestigeTotal').value;
        var PrestigeMax = document.getElementById('txtPrestigeMax').value;
        var XpSaldo = document.getElementById('txtTotalXP'). value;
        var XpMax = document.getElementById('txtMaxXP').value;
        var SpSaldo = document.getElementById('txtTotalSP').value;
        var SpMax = document.getElementById('txtMaxSP').value;
        var Earnings = Number(document.getElementById('txtFinRevenue').value) + Number(document.getElementById('txtFinSalary').value) - Number(document.getElementById('txtFinSalaryEmp').value);
        var Endowment = document.getElementById('txtFinEndowment').value;
        var Savings = document.getElementById('txtFinSavings').value;
        var GreenRings = document.getElementById('txtGreen').value;
        var YellowRings = document.getElementById('txtYellow').value;
        var RedRings = document.getElementById('txtRed').value;
        var WhiteRings = document.getElementById('txtWhite').value;
        var Split = document.getElementById('txtSplit').value;
        
        //Skills
        if (document.getElementById('txt_sCode1').value != ""){var sAcademicus = document.getElementById('txt_sCode1').value;}
        if (document.getElementById('txt_sCode2').value != ""){var sAmbachten = document.getElementById('txt_sCode2').value;}
        if (document.getElementById('txt_sCode3').value != ""){var sAstronomie = document.getElementById('txt_sCode3').value;}
        if (document.getElementById('txt_sCode4').value != ""){var sBedriegerij = document.getElementById('txt_sCode4').value;}
        if (document.getElementById('txt_sCode5').value != ""){var sBiologie = document.getElementById('txt_sCode5').value;}
        if (document.getElementById('txt_sCode6').value != ""){var sBoogschieten = document.getElementById('txt_sCode6').value;}
        if (document.getElementById('txt_sCode7').value != ""){var sEconomie = document.getElementById('txt_sCode7').value;}
        if (document.getElementById('txt_sCode8').value != ""){var sGeneeskunde = document.getElementById('txt_sCode8').value;}
        if (document.getElementById('txt_sCode9').value != ""){var sHypnose = document.getElementById('txt_sCode9').value;}
        if (document.getElementById('txt_sCode10').value != ""){var sIngenieur = document.getElementById('txt_sCode10').value;}
        if (document.getElementById('txt_sCode11').value != ""){var sKunsten = document.getElementById('txt_sCode11').value;}
        if (document.getElementById('txt_sCode12').value != ""){var sLiturgie = document.getElementById('txt_sCode12').value;}
        if (document.getElementById('txt_sCode13').value != ""){var sMachinetaal = document.getElementById('txt_sCode13').value;}
        if (document.getElementById('txt_sCode14').value != ""){var sMacht = document.getElementById('txt_sCode14').value;}
        if (document.getElementById('txt_sCode15').value != ""){var sMartelen = document.getElementById('txt_sCode15').value;}
        if (document.getElementById('txt_sCode16').value != ""){var sNatuurkunde = document.getElementById('txt_sCode16').value;}
        if (document.getElementById('txt_sCode17').value != ""){var sOccultisme = document.getElementById('txt_sCode17').value;}
        if (document.getElementById('txt_sCode18').value != ""){var sOndervraging = document.getElementById('txt_sCode18').value;}
        if (document.getElementById('txt_sCode19').value != ""){var sPadvinderij = document.getElementById('txt_sCode19').value;}
        if (document.getElementById('txt_sCode20').value != ""){var sPiloot = document.getElementById('txt_sCode20').value;}
        if (document.getElementById('txt_sCode21').value != ""){var sPsychologie = document.getElementById('txt_sCode21').value;}
        if (document.getElementById('txt_sCode22').value != ""){var sRechtsleer = document.getElementById('txt_sCode22').value;}
        if (document.getElementById('txt_sCode23').value != ""){var sSchermen = document.getElementById('txt_sCode23').value;}
        if (document.getElementById('txt_sCode24').value != ""){var sScheikunde = document.getElementById('txt_sCode24').value;}
        if (document.getElementById('txt_sCode25').value != ""){var sSpeuren = document.getElementById('txt_sCode25').value;}
        if (document.getElementById('txt_sCode26').value != ""){var sSpiritisme = document.getElementById('txt_sCode26').value;}
        if (document.getElementById('txt_sCode27').value != ""){var sSubtiliteit = document.getElementById('txt_sCode27').value;}
        if (document.getElementById('txt_sCode28').value != ""){var sTaaiheid = document.getElementById('txt_sCode28').value;}
        if (document.getElementById('txt_sCode29').value != ""){var sTacticus = document.getElementById('txt_sCode29').value;}
        if (document.getElementById('txt_sCode30').value != ""){var sVuurwapens = document.getElementById('txt_sCode30').value;}
        if (document.getElementById('txt_sCode31').value != ""){var sWapenkunde = document.getElementById('txt_sCode31').value;}
        if (document.getElementById('txt_sCode32').value != ""){var sWereldwijs = document.getElementById('txt_sCode32').value;}
        if (document.getElementById('txt_sCode33').value != ""){var sWerpwapens = document.getElementById('txt_sCode33').value;}
        if (document.getElementById('txt_sCode34').value != ""){var sWilskracht = document.getElementById('txt_sCode34').value;}
        if (document.getElementById('txt_sCode35').value != ""){var sUniek1 = document.getElementById('txt_sCode35').value;}
        if (document.getElementById('txt_sCode36').value != ""){var sUniek2 = document.getElementById('txt_sCode36').value;}
        if (document.getElementById('txt_sCode37').value != ""){var sUniek3 = document.getElementById('txt_sCode37').value;}
        
        var SkillCount = 0;
        var arraySkillLvlOriginal = [];
        var arraySkillAbr = ["Acad", "Amba", "Astr", "Bedr", "Biol", "Boog", "Econ", "Gene", "Hypn", "Inge", "Kuns", "Litu", "Mach", "Migh", "Mart", "Natu", "Occu", "Onde", "Padv", "Pilo", "Psyc", "Rech", "Sche", "Chem", "Speu", "Spir", "Subt", "Taai", "Tact", "Vuur", "Wape", "Were", "Werp", "Wils", "Godv", "Weze", "Tech"];
        var SkillTotal = arraySkillAbr.length;
        
        var Acad = document.getElementById('txt_sLevel1').value;
        var Amba = document.getElementById('txt_sLevel2').value;
        var Astr = document.getElementById('txt_sLevel3').value;
        var Bedr = document.getElementById('txt_sLevel4').value;
        var Biol = document.getElementById('txt_sLevel5').value;
        var Boog = document.getElementById('txt_sLevel6').value;
        var Econ = document.getElementById('txt_sLevel7').value;
        var Gene = document.getElementById('txt_sLevel8').value;
        var Hypn = document.getElementById('txt_sLevel9').value;
        var Inge = document.getElementById('txt_sLevel10').value;
        var Kuns = document.getElementById('txt_sLevel11').value;
        var Litu = document.getElementById('txt_sLevel12').value;
        var Mach = document.getElementById('txt_sLevel13').value;
        var Migh = document.getElementById('txt_sLevel14').value;
        var Mart = document.getElementById('txt_sLevel15').value;
        var Natu = document.getElementById('txt_sLevel16').value;
        var Occu = document.getElementById('txt_sLevel17').value;
        var Onde = document.getElementById('txt_sLevel18').value;
        var Padv = document.getElementById('txt_sLevel19').value;
        var Pilo = document.getElementById('txt_sLevel20').value;
        var Psyc = document.getElementById('txt_sLevel21').value;
        var Rech = document.getElementById('txt_sLevel22').value;
        var Sche = document.getElementById('txt_sLevel23').value;
        var Chem = document.getElementById('txt_sLevel24').value;
        var Speu = document.getElementById('txt_sLevel25').value;
        var Spir = document.getElementById('txt_sLevel26').value;
        var Subt = document.getElementById('txt_sLevel27').value;
        var Taai = document.getElementById('txt_sLevel28').value;
        var Tact = document.getElementById('txt_sLevel29').value;
        var Vuur = document.getElementById('txt_sLevel30').value;
        var Wape = document.getElementById('txt_sLevel31').value;
        var Were = document.getElementById('txt_sLevel32').value;
        var Werp = document.getElementById('txt_sLevel33').value;
        var Wils = document.getElementById('txt_sLevel34').value;
        var Uni1 = document.getElementById('txt_sLevel35').value;
        var Uni2 = document.getElementById('txt_sLevel36').value;
        var Uni3 = document.getElementById('txt_sLevel37').value;
        
        if (Acad > 0){SkillCount = SkillCount + 1;}
        if (Amba > 0){SkillCount = SkillCount + 1;}
        if (Astr > 0){SkillCount = SkillCount + 1;}
        if (Bedr > 0){SkillCount = SkillCount + 1;}
        if (Biol > 0){SkillCount = SkillCount + 1;}
        if (Boog > 0){SkillCount = SkillCount + 1;}
        if (Econ > 0){SkillCount = SkillCount + 1;}
        if (Gene > 0){SkillCount = SkillCount + 1;}
        if (Hypn > 0){SkillCount = SkillCount + 1;}
        if (Inge > 0){SkillCount = SkillCount + 1;}
        if (Kuns > 0){SkillCount = SkillCount + 1;}
        if (Litu > 0){SkillCount = SkillCount + 1;}
        if (Mach > 0){SkillCount = SkillCount + 1;}
        if (Migh > 0){SkillCount = SkillCount + 1;}
        if (Mart > 0){SkillCount = SkillCount + 1;}
        if (Natu > 0){SkillCount = SkillCount + 1;}
        if (Occu > 0){SkillCount = SkillCount + 1;}
        if (Onde > 0){SkillCount = SkillCount + 1;}
        if (Padv > 0){SkillCount = SkillCount + 1;}
        if (Pilo > 0){SkillCount = SkillCount + 1;}
        if (Psyc > 0){SkillCount = SkillCount + 1;}
        if (Rech > 0){SkillCount = SkillCount + 1;}
        if (Sche > 0){SkillCount = SkillCount + 1;}
        if (Chem > 0){SkillCount = SkillCount + 1;}
        if (Speu > 0){SkillCount = SkillCount + 1;}
        if (Spir > 0){SkillCount = SkillCount + 1;}
        if (Subt > 0){SkillCount = SkillCount + 1;}
        if (Taai > 0){SkillCount = SkillCount + 1;}
        if (Tact > 0){SkillCount = SkillCount + 1;}
        if (Vuur > 0){SkillCount = SkillCount + 1;}
        if (Wape > 0){SkillCount = SkillCount + 1;}
        if (Were > 0){SkillCount = SkillCount + 1;}
        if (Werp > 0){SkillCount = SkillCount + 1;}
        if (Wils > 0){SkillCount = SkillCount + 1;}
        if (Uni1 > 0){SkillCount = SkillCount + 1;}
        if (Uni2 > 0){SkillCount = SkillCount + 1;}
        if (Uni3 > 0){SkillCount = SkillCount + 1;}
        
        //ODD SKILLCOUNT and PAGES
        var iniSkillCount = SkillCount;
        var SkillDivs;
        var oddSkillArray = [3,5,7,9,11,13,15,17,19,21,23,25,27,29,31,33,35,37];
        var oddPageArray = [1,2,5,6,9,10,13,14,17,18,21,22,25,26,29,30,33,34];
        
        if (oddSkillArray.indexOf(SkillCount) != -1){ 
            SkillDivs = SkillCount + 1;// this eliminates the effect of uneven skillcounts
            iniSkillDivs = SkillDivs;
            if (oddPageArray.indexOf(SkillDivs) != -1){
                SkillDivs = SkillDivs + 2;// this eliminates the effect of uneven pages
                SkillCount = SkillCount + 2;
            }       
        }        
        else {
            SkillDivs = SkillCount;
            iniSkillDivs = SkillDivs;
            if (oddPageArray.indexOf(SkillDivs) != -1){
                SkillDivs = SkillDivs + 2;// this eliminates the effect of uneven pages
                SkillCount = SkillCount + 2;
            } 
        }
        
        var AcadDesc = document.getElementById('txt_sDesc1').innerHTML;
        var AmbaDesc = document.getElementById('txt_sDesc2').innerHTML;
        var AstrDesc = document.getElementById('txt_sDesc3').innerHTML;
        var BedrDesc = document.getElementById('txt_sDesc4').innerHTML;
        var BiolDesc = document.getElementById('txt_sDesc5').innerHTML;
        var BoogDesc = document.getElementById('txt_sDesc6').innerHTML;
        var EconDesc = document.getElementById('txt_sDesc7').innerHTML;
        var GeneDesc = document.getElementById('txt_sDesc8').innerHTML;
        var HypnDesc = document.getElementById('txt_sDesc9').innerHTML;
        var IngeDesc = document.getElementById('txt_sDesc10').innerHTML;
        var KunsDesc = document.getElementById('txt_sDesc11').innerHTML;
        var LituDesc = document.getElementById('txt_sDesc12').innerHTML;
        var MachDesc = document.getElementById('txt_sDesc13').innerHTML;
        var MighDesc = document.getElementById('txt_sDesc14').innerHTML;
        var MartDesc = document.getElementById('txt_sDesc15').innerHTML;
        var NatuDesc = document.getElementById('txt_sDesc16').innerHTML;
        var OccuDesc = document.getElementById('txt_sDesc17').innerHTML;
        var OndeDesc = document.getElementById('txt_sDesc18').innerHTML;
        var PadvDesc = document.getElementById('txt_sDesc19').innerHTML;
        var PiloDesc = document.getElementById('txt_sDesc20').innerHTML;
        var PsycDesc = document.getElementById('txt_sDesc21').innerHTML;
        var RechDesc = document.getElementById('txt_sDesc22').innerHTML;
        var ScheDesc = document.getElementById('txt_sDesc23').innerHTML;
        var ChemDesc = document.getElementById('txt_sDesc24').innerHTML;
        var SpeuDesc = document.getElementById('txt_sDesc25').innerHTML;
        var SpirDesc = document.getElementById('txt_sDesc26').innerHTML;
        var SubtDesc = document.getElementById('txt_sDesc27').innerHTML;
        var TaaiDesc = document.getElementById('txt_sDesc28').innerHTML;
        var TactDesc = document.getElementById('txt_sDesc29').innerHTML;
        var VuurDesc = document.getElementById('txt_sDesc30').innerHTML;
        var WapeDesc = document.getElementById('txt_sDesc31').innerHTML;
        var WereDesc = document.getElementById('txt_sDesc32').innerHTML;
        var WerpDesc = document.getElementById('txt_sDesc33').innerHTML;
        var WilsDesc = document.getElementById('txt_sDesc34').innerHTML;
        var Uni1Desc = document.getElementById('txt_sDesc35').innerHTML;
        var Uni2Desc = document.getElementById('txt_sDesc36').innerHTML;
        var Uni3Desc = document.getElementById('txt_sDesc37').innerHTML;
        
        var SkillTotal = arraySkillAbr.length;   //to be edited (schemerwezen, techn intuitie, ...)
        
        //Unique skills - Name
        var Uni1Name = document.getElementById('txt_sName35').value;
        var Uni2Name = document.getElementById('txt_sName36').value;
        var Uni3Name = document.getElementById('txt_sName37').value;
        
        //Traits
        //
        var TraitName1 = 0;
        var TraitName2 = 0;
        var TraitName3 = 0;
        var TraitName4 = 0;
        var TraitName5 = 0;
        var TraitName6 = 0;
        var TraitName7 = 0;
        var TraitName8 = 0;
        var TraitName9 = 0;
        var TraitName10 = 0;
        var TraitName11 = 0;
        var TraitName12 = 0;
        var TraitName13 = 0;
        var TraitName14 = 0;
        var TraitName15 = 0;
        
        var TraitDesc1 = 0;
        var TraitDesc2 = 0;
        var TraitDesc3 = 0;
        var TraitDesc4 = 0;
        var TraitDesc5 = 0;
        var TraitDesc6 = 0;
        var TraitDesc7 = 0;
        var TraitDesc8 = 0;
        var TraitDesc9 = 0;
        var TraitDesc10 = 0;
        var TraitDesc11 = 0;
        var TraitDesc12 = 0;
        var TraitDesc13 = 0;
        var TraitDesc14 = 0;
        var TraitDesc15 = 0;
        
        var iTrait;
        var aTraitName = [TraitName1,TraitName2,TraitName3,TraitName4,TraitName5,TraitName6,TraitName7,TraitName8,TraitName9,TraitName10,TraitName11,TraitName12,TraitName13,TraitName14,TraitName15];
        var aTraitDesc = [TraitDesc1,TraitDesc2,TraitDesc3,TraitDesc4,TraitDesc5,TraitDesc6,TraitDesc7,TraitDesc8,TraitDesc9,TraitDesc10,TraitDesc11,TraitDesc12,TraitDesc13,TraitDesc14,TraitDesc15];
        var aIndexTrait = 0;
        
        for (iTrait = 1; iTrait < 15; iTrait++){
            if (document.getElementById('txt_TraitName'+iTrait) == undefined){
                aTraitName[aIndexTrait] = 0; 
                aTraitDesc[aIndexTrait] = 0;
                localStorage.setItem("traitname"+iTrait, aTraitName[aIndexTrait]);
                localStorage.setItem("traitdesc"+iTrait, aTraitDesc[aIndexTrait]);
            } 
            else {
                aTraitName[aIndexTrait] = document.getElementById('txt_TraitName' + iTrait).value;
                aTraitDesc[aIndexTrait] = document.getElementById('txt_TraitDesc' + iTrait).innerHTML;
                localStorage.setItem("traitname"+iTrait, aTraitName[aIndexTrait]);
                localStorage.setItem("traitdesc"+iTrait, aTraitDesc[aIndexTrait]);
            }
            aIndexTrait = aIndexTrait + 1;
        }
        
        //Languages
        var Language1 = 0;
        var Language2 = 0;
        var Language3 = 0;
        var Language4 = 0;
        var Language5 = 0;
        var Language6 = 0;
        var Language7 = 0;
        var Language8 = 0;
        var Language9 = 0;
        var Language10 = 0;
        
        var xLanguage;
        var aLanguage = [Language1,Language2,Language3,Language4,Language5,Language6,Language7,Language8,Language9,Language10];
        var aIndexLang = 0;
            
        for (xLanguage = 1; xLanguage < 10; xLanguage++){
            if (document.getElementById('txt_Language'+xLanguage) == undefined){
                aLanguage[aIndexLang] = 0;
                localStorage.setItem("language"+xLanguage, aLanguage[aIndexLang]);
            }
            else {
                aLanguage[aIndexLang] = document.getElementById('txt_Language'+xLanguage).value;
                localStorage.setItem("language"+xLanguage, aLanguage[aIndexLang]);
            }
            aIndexLang = aIndexLang + 1;
        }
        
        //Licences
        var Licence1 = 0;
        var Licence2 = 0;
        var Licence3 = 0;
        var Licence4 = 0;
        var Licence5 = 0;
        var Licence6 = 0;
        var Licence7 = 0;
        var Licence8 = 0;
        var Licence9 = 0;
        var Licence10 = 0;
        
        var xLicence;
        var aLicence = [Licence1,Licence2,Licence3,Licence4,Licence5,Licence6,Licence7,Licence8,Licence9,Licence10];
        var aIndex = 0;
            
        for (xLicence = 1; xLicence < 10; xLicence++){
            if (document.getElementById('txt_Licence'+xLicence) == undefined){
                aLicence[aIndex] = 0;
                localStorage.setItem("licence"+xLicence, aLicence[aIndex]);
            }
            else {
                aLicence[aIndex] = document.getElementById('txt_Licence'+xLicence).value;
                localStorage.setItem("licence"+xLicence, aLicence[aIndex]);
            }
            aIndex = aIndex + 1;
        }
    /**/    
        //STORE VALUES
        localStorage.setItem("fname", Fname);
        localStorage.setItem("lname", Lname);
        localStorage.setItem("userid", UserId); 
        //localStorage.setItem("class", Class); 
        localStorage.setItem("gender", Gender); 
        localStorage.setItem("birthplace", BirthPlace); 
        localStorage.setItem("birthdate", BirthDate); 
        localStorage.setItem("nationality", Nationality); 
        localStorage.setItem("regnumber", RegNumber); 
        localStorage.setItem("street", Street); 
        localStorage.setItem("housenumber", HouseNumber); 
        localStorage.setItem("postalcode", PostalCode); 
        localStorage.setItem("city", City); 
        //localStorage.setItem("title", Title); 
        localStorage.setItem("profession", Profession); 
        localStorage.setItem("socialstatus", SocialStatus);
        localStorage.setItem("prestigesaldo", PrestigeSaldo);
        localStorage.setItem("prestigemax", PrestigeMax);
        localStorage.setItem("xpsaldo", XpSaldo); 
        localStorage.setItem("xpmax", XpMax); 
        localStorage.setItem("split", Split);
        localStorage.setItem("earnings", Earnings);
        localStorage.setItem("endowment", Endowment);
        localStorage.setItem("savings", Savings);
        
        localStorage.setItem("greenrings", GreenRings);
        localStorage.setItem("yellowrings", YellowRings);
        localStorage.setItem("redrings", RedRings);
        localStorage.setItem("whiterings", WhiteRings);
        
        localStorage.setItem("skillcount", SkillCount);
        localStorage.setItem("skilldivs", SkillDivs);
        localStorage.setItem("skilltotal", SkillTotal);
        localStorage.setItem("iniskillcount", iniSkillCount);
        localStorage.setItem("iniskilldivs", iniSkillDivs);
        
        localStorage.setItem("acadname", sAcademicus);
        localStorage.setItem("ambaname", sAmbachten);
        localStorage.setItem("astrname", sAstronomie);
        localStorage.setItem("bedrname", sBedriegerij);
        localStorage.setItem("biolname", sBiologie);
        localStorage.setItem("boogname", sBoogschieten);
        localStorage.setItem("econname", sEconomie);
        localStorage.setItem("genename", sGeneeskunde);
        localStorage.setItem("hypnname", sHypnose);
        localStorage.setItem("ingename", sIngenieur);
        localStorage.setItem("kunsname", sKunsten);
        localStorage.setItem("lituname", sLiturgie);
        localStorage.setItem("machname", sMachinetaal);
        localStorage.setItem("mighname", sMacht);
        localStorage.setItem("martname", sMartelen);
        localStorage.setItem("natuname", sNatuurkunde);
        localStorage.setItem("occuname", sOccultisme);
        localStorage.setItem("ondename", sOndervraging);
        localStorage.setItem("padvname", sPadvinderij);
        localStorage.setItem("piloname", sPiloot);
        localStorage.setItem("psycname", sPsychologie);
        localStorage.setItem("rechname", sRechtsleer);
        localStorage.setItem("schename", sSchermen);
        localStorage.setItem("chemname", sScheikunde);
        localStorage.setItem("speuname", sSpeuren);
        localStorage.setItem("spirname", sSpiritisme);
        localStorage.setItem("subtname", sSubtiliteit);
        localStorage.setItem("taainame", sTaaiheid);
        localStorage.setItem("tactname", sTacticus);
        localStorage.setItem("vuurname", sVuurwapens);
        localStorage.setItem("wapename", sWapenkunde);
        localStorage.setItem("werename", sWereldwijs);
        localStorage.setItem("werpname", sWerpwapens);
        localStorage.setItem("wilsname", sWilskracht);
        localStorage.setItem("uni1name", sUniek1);
        localStorage.setItem("uni2name", sUniek2);
        localStorage.setItem("uni3name", sUniek3);
      
        localStorage.setItem("acadlvl", Acad);
        localStorage.setItem("ambalvl", Amba);
        localStorage.setItem("astrlvl", Astr);
        localStorage.setItem("bedrlvl", Bedr);
        localStorage.setItem("biollvl", Biol);
        localStorage.setItem("booglvl", Boog);
        localStorage.setItem("econlvl", Econ);
        localStorage.setItem("genelvl", Gene);
        localStorage.setItem("hypnlvl", Hypn);
        localStorage.setItem("ingelvl", Inge);
        localStorage.setItem("kunslvl", Kuns);
        localStorage.setItem("litulvl", Litu);
        localStorage.setItem("machlvl", Mach);
        localStorage.setItem("mighlvl", Migh);
        localStorage.setItem("martlvl", Mart);
        localStorage.setItem("natulvl", Natu);
        localStorage.setItem("occulvl", Occu);
        localStorage.setItem("ondelvl", Onde);
        localStorage.setItem("padvlvl", Padv);
        localStorage.setItem("pilolvl", Pilo);
        localStorage.setItem("psyclvl", Psyc);
        localStorage.setItem("rechlvl", Rech);
        localStorage.setItem("schelvl", Sche);
        localStorage.setItem("chemlvl", Chem);
        localStorage.setItem("speulvl", Speu);
        localStorage.setItem("spirlvl", Spir);
        localStorage.setItem("subtlvl", Subt);
        localStorage.setItem("taailvl", Taai);
        localStorage.setItem("tactlvl", Tact);
        localStorage.setItem("vuurlvl", Vuur);
        localStorage.setItem("wapelvl", Wape);
        localStorage.setItem("werelvl", Were);
        localStorage.setItem("werplvl", Werp);
        localStorage.setItem("wilslvl", Wils);
        localStorage.setItem("uni1lvl", Uni1);
        localStorage.setItem("uni2lvl", Uni2);
        localStorage.setItem("uni3lvl", Uni3);
        
        localStorage.setItem("acaddesc", AcadDesc);
        localStorage.setItem("ambadesc", AmbaDesc);
        localStorage.setItem("astrdesc", AstrDesc);
        localStorage.setItem("bedrdesc", BedrDesc);
        localStorage.setItem("bioldesc", BiolDesc);
        localStorage.setItem("boogdesc", BoogDesc);
        localStorage.setItem("econdesc", EconDesc);
        localStorage.setItem("genedesc", GeneDesc);
        localStorage.setItem("hypndesc", HypnDesc);
        localStorage.setItem("ingedesc", IngeDesc);
        localStorage.setItem("kunsdesc", KunsDesc);
        localStorage.setItem("litudesc", LituDesc);
        localStorage.setItem("machdesc", MachDesc);
        localStorage.setItem("mighdesc", MighDesc);
        localStorage.setItem("martdesc", MartDesc);
        localStorage.setItem("natudesc", NatuDesc);
        localStorage.setItem("occudesc", OccuDesc);
        localStorage.setItem("ondedesc", OndeDesc);
        localStorage.setItem("padvdesc", PadvDesc);
        localStorage.setItem("pilodesc", PiloDesc);
        localStorage.setItem("psycdesc", PsycDesc);
        localStorage.setItem("rechdesc", RechDesc);
        localStorage.setItem("schedesc", ScheDesc);
        localStorage.setItem("chemdesc", ChemDesc);
        localStorage.setItem("speudesc", SpeuDesc);
        localStorage.setItem("spirdesc", SpirDesc);
        localStorage.setItem("subtdesc", SubtDesc);
        localStorage.setItem("taaidesc", TaaiDesc);
        localStorage.setItem("tactdesc", TactDesc);
        localStorage.setItem("vuurdesc", VuurDesc);
        localStorage.setItem("wapedesc", WapeDesc);
        localStorage.setItem("weredesc", WereDesc);
        localStorage.setItem("werpdesc", WerpDesc);
        localStorage.setItem("wilsdesc", WilsDesc);
        localStorage.setItem("uni1desc", Uni1Desc);
        localStorage.setItem("uni2desc", Uni2Desc);
        localStorage.setItem("uni3desc", Uni3Desc);
        
        localStorage.setItem("uni1name", Uni1Name);
        localStorage.setItem("uni2name", Uni2Name);
        localStorage.setItem("uni3name", Uni3Name);
        
    /* */  
    }
        
// CREATE NEW PASSPORT
//Open new tab and execute function retrieve data

function CreatePassport(){
    StoreData();
    //window.open('http://www.digitalthomas.net/Oneiros/aether/ACS_master_passport.html','_blank');
    //RetrieveData(); 
}	
	