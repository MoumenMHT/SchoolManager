import json

with open('excel_data.json', 'r', encoding='utf-8') as f:
    data = json.load(f)

students = data['students']
teachers = data['teachers']

def php_val(v):
    if v is None: return 'null'
    s = str(v).replace("'", "\\'")
    return "'" + s + "'"

php_students = '[\n'
for s in students:
    line = "    ["
    line += "'reg_no' => " + php_val(s['reg_no']) + ", "
    line += "'last_name' => " + php_val(s['last_name']) + ", "
    line += "'first_name' => " + php_val(s['first_name']) + ", "
    line += "'father_name' => " + php_val(s['father_name']) + ", "
    line += "'class' => " + php_val(s['class']) + ", "
    line += "'birth_date' => " + php_val(s['birth_date']) + ", "
    line += "'birth_place' => " + php_val(s['birth_place']) + ", "
    line += "'address' => " + php_val(s['address'])
    line += "],\n"
    php_students += line
php_students += ']'

php_teachers = '[\n'
for t in teachers:
    line = "    ["
    line += "'name' => " + php_val(t['name']) + ", "
    line += "'subject' => " + php_val(t['subject']) + ", "
    line += "'band' => " + php_val(t['band'])
    line += "],\n"
    php_teachers += line
php_teachers += ']'

with open('students_php.txt', 'w', encoding='utf-8') as f: f.write(php_students)
with open('teachers_php.txt', 'w', encoding='utf-8') as f: f.write(php_teachers)