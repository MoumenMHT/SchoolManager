import json

with open('excel_data.json', 'r', encoding='utf-8') as f:
    data = json.load(f)

students_list = data.get('قوائم التلاميد', [])
teachers_list = data.get('قوائم الاساتدة', [])

def escape_php(s):
    if not isinstance(s, str): return str(s)
    return s.replace("'", "\\'")

output = "<?php\n\n"

output += "$excel_students = [\n"
for s in students_list:
    reg_no = s.get('Unnamed: 1')
    if not reg_no or str(reg_no) in ['التسجيل', 'رقم التسجيل']: continue
    last_name = escape_php(str(s.get(next((k for k in s.keys() if 'تلاميذ' in k or 'اللقب' in k), ''), '')))
    first_name = escape_php(str(s.get('Unnamed: 3', '')))
    father_name = escape_php(str(s.get('Unnamed: 4', '')))
    class_name = escape_php(str(s.get('Unnamed: 5', '')))
    birth_date = str(s.get('Unnamed: 6', ''))[:10]
    birth_place = escape_php(str(s.get('Unnamed: 7', '')))
    address = escape_php(str(s.get('Unnamed: 8', '')))
    output += f"    ['reg_no' => '{reg_no}', 'last_name' => '{last_name}', 'first_name' => '{first_name}', 'father_name' => '{father_name}', 'class' => '{class_name}', 'birth_date' => '{birth_date}', 'birth_place' => '{birth_place}', 'address' => '{address}'],\n"
output += "];\n\n"

output += "$excel_teachers = [\n"
for t in teachers_list:
    for subj_key, name_key in [('Unnamed: 1', 'Unnamed: 2'), ('Unnamed: 4', 'Unnamed: 5')]:
        subj = t.get(subj_key)
        name = t.get(name_key)
        if subj and name and 'المادة' not in str(subj):
            output += f"    ['full_name' => '{escape_php(str(name))}', 'subject' => '{escape_php(str(subj))}'],\n"
output += "];\n"

with open('excel_data_snippets.php', 'w', encoding='utf-8') as f:
    f.write(output)
