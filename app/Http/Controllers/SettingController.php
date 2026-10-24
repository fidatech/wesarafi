<?php
namespace App\Http\Controllers;
use App\Models\Setting; use Illuminate\Http\Request; use Illuminate\Support\Facades\Storage;
class SettingController extends Controller {
 public function index(){return view('configuration.settings.index',['setting'=>Setting::firstOrCreate([])]);}
 public function update(Request $r){$d=$r->validate(['company_name'=>'required|string|max:255','license_number'=>'nullable|string|max:255','phone'=>'nullable|string|max:50','email'=>'nullable|email','address'=>'nullable|string|max:1000','report_footer'=>'nullable|string|max:1000','default_language'=>'nullable|in:en,fa,ps','timezone'=>'nullable|string|max:100','date_format'=>'nullable|string|max:30','logo'=>'nullable|image|max:3072']);$s=Setting::firstOrCreate([]);if($r->hasFile('logo')){$d['logo']=$r->file('logo')->store('settings','public');} $s->update($d);return back()->with('success','System settings updated.');}
}
