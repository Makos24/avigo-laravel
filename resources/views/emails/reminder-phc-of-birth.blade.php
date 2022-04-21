<!DOCTYPE html>
<html>
<head></head>
<body>
    
    Dear Management of <b>{{ $phc_name }}</b>, 
    <br>This is to remind you of new births registered with your facility last weekend. Do well to get this baby(s) immunized as soon as possible.
    <br>For more details on these deliveries click the following link <a href="{{ URL('').'/login-redirect?publicHealthLogin=yes&FacID='.urlencode($phc_id).'&phone='.urlencode($phc_phone).'&facilityUID='.urlencode($phc_facility_uid) }}"><b>Get full birth report</b></a>.
    <br><br>Warm regards.
    <br><br><br>Avigo Health Dev Team
    <div style="color: #202020; font-size:11px; width: 100%;">
      <div style=" width: 100%;display:flex;text-align: center;">Avigo Health L.L.C.</div>
      <div style=" width: 100%;display:flex;text-align: center;">
        1717 Pennsylvania Ave Suite 1025
        Washington, DC 20006
      </div>
      <div style=" width: 100%;display:flex;text-align: center;">info@avigohealth.com</div>
      <div style=" width: 100%;display:flex;text-align: center;">+1 617.528.9495</div>
    </div>
</body>
</html>