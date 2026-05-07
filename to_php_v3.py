import json

def escape_php(s):
    if not isinstance(s, str): return str(s)
    return s.replace("'", "\\'")

with open('excel_data.json', 'r', encoding='utf-8') as f:
    data = json.load(f)

students_list = data.get('قوائم التلاميد', [])
teachers_list = data.get('قوائم الاساتدة', [])

with open('students_php.txt', 'w', encoding='utf-8') as f:
    f.write('[\n')
    for s in students_list:
        reg_no = s.get('Unnamed: 1')
        if not reg_no or str(reg_no) in ['التسجيل', 'رقم التسجيل']: continue
        
        last_name = ''
        first_name = ''
        name_key = next((k for k in s.keys() if 'تلاميذ' in k or 'اللقب' in k), None)
        if name_key:
            last_name = s.get(name_key, '')
        
        first_name = s.get('Unnamed: 3', '')
        father_name = s.get('Unnamed: 4', '')
        class_name = s.get('Unnamed: 5', '')
        birth_date = s.get('Unnamed: 6', '')
        birth_place = s.get('Unnamed: 7', '')
        address = s.get('Unnamed: 8', '')
        
        line = "    ['reg_no' => '{}', 'last_name' => '{}', 'first_name' => '{}', 'father_name' => '{}', 'class' => '{}', 'birth_date' => '{}', 'birth_place' => '{}', 'address' => '{}'],\n".format(
            escape_php(str(reg_no)),
            escape_php(str(last_name)),
            escape_php(str(first_name)),
            escape_php(str(father_name)),
            escape_php(str(class_name)),
            str(birth_date),
            escape_php(str(birth_place)),
            escape_php(str(address))
        )
        f.write(line)
    f.write(']')

with open('teachers_php.txt', 'w', encoding='utf-8') as f:
    f.write('[\n')
    for t in teachers_list:
        # Check first set (columns 1 and 2)
        subj1 = t.get('Unnamed: 1')
        name1 = t.get('Unnamed: 2')
        if subj1 and name1 and 'المادة' not in str(subj1):
            line = "    ['full_name' => '{}', 'subject' => '{}'],\n".format(escape_php(str(name1)), escape_php(str(subj1)))
            f.write(line)
        
        # Check second set (columns 4 and 5)
        subj2 = t.get('Unnamed: 4')
        name2 = t.get('Unnamed: 5')
        if subj2 and name2 and 'المادة' not in str(subj2):
            line = "    ['full_name' => '{}', 'subject' => '{}'],\n".format(escape_php(str(name2)), escape_php(str(subj2)))
            f.write(line)
    f.write(']')
