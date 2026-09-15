const navSheetAdmin = `
        <ul class="nav nav-tabs" id="navSheet">
            <li class="nav-item"><a class="nav-link active navTab" aria-current="page" href="#">Personagekaart</a></li>
            <li class="nav-item"><a class="nav-link navTab" href="#">Achtergrond</a></li>
            <li class="nav-item"><a class="nav-link navTab" href="#">Dagboek</a></li>
        </ul>
        <div class="row py-2 navRow">
            <div class="col-lg-6 col-12 row">
                <div class="col-sm-3 col-form-label">Deelnemer</div>
                <div class="col-sm-9" id="listParticipant"></div>
            </div>
            <div class="col-lg-6 col-12 row">
                <label class="col-sm-3 col-form-label">Type personage</label>
                <div class="col-sm-9">
                    <select class="form-select" id="type" name="type">
                        <option value="player">Spelerspersonage</option>
                        <option value="extra">Figurantenrol</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="row pb-2 border-bottom mb-4 navRow">
            <div class="col-lg-6 col-12 row">
                <div class="col-sm-3 col-form-label">Ervaringspunten</div>
                <div class="col-sm-9 col-form-label" id="lblExperiance"></div>
            </div>
            <div class="col-lg-6 col-12 row">
                <label class="col-sm-3 col-form-label">Status</label>
                <div class="col-sm-9">
                    <select class="form-select" id="state" name="state">
                        <option value="active">Actief</option>
                        <option value="inactive">Inactief</option>
                        <option value="deceased">Overleden</option>
                        <option value="other">Speciaal</option>
                    </select>
                </div>
            </div>
        </div>

`;

const navSheetdParticipant = `
        <ul class="nav nav-tabs" id="navSheet">
            <li class="nav-item"><a class="nav-link active navTab" aria-current="page" href="#">Personagekaart</a></li>
            <li class="nav-item"><a class="nav-link navTab" href="#">Achtergrond</a></li>
            <li class="nav-item"><a class="nav-link navTab" href="#">Dagboek</a></li>
        </ul>
        <div class="row py-2 navRow" id="adminSheetHead">
            <div class="col-lg-6 col-12 row">
                <div class="col-sm-3 col-form-label">Deelnemer</div>
                <div class="col-sm-9 col-form-label" id="nameParticipant"></div>
            </div>
            <div class="col-lg-6 col-12 row">
                <label class="col-sm-3 col-form-label">Type personage</label>
                <div class="col-sm-9 col-form-label" id="type"></div>
            </div>
        </div>
        <div class="row pb-2 border-bottom mb-4 navRow" id="sheetHead">
            <div class="col-lg-6 col-12 row">
                <div class="col-sm-3 col-form-label">Ervaringspunten</div>
                <div class="col-sm-9 col-form-label" id="lblExperiance"></div>
            </div>
            <div class="col-lg-6 col-12 row">
                <label class="col-sm-3 col-form-label">Status</label>
                <div class="col-sm-9 col-form-label" id="state"></div>
            </div>
        </div>
`;