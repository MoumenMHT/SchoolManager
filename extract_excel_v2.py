import pandas as pd
import json
import sys
import glob

import datetime

def serialize(obj):
    if isinstance(obj, (pd.Timestamp, datetime.datetime, datetime.date)):
        return obj.isoformat()
    if pd.isna(obj):
        return None
    return obj

files = glob.glob(r'C:\Users\issam\Downloads\*.xlsx')
target_file = next((f for f in files if 'قائمة الإسمية' in f), None)

if not target_file:
    print('Error: Target file not found', file=sys.stderr)
    sys.exit(1)

try:
    xl = pd.ExcelFile(target_file)
    data = {}
    for sheet_name in xl.sheet_names:
        df = xl.parse(sheet_name)
        records = []
        for _, row in df.iterrows():
            record = {col: serialize(val) for col, val in row.items()}
            records.append(record)
        data[sheet_name] = records
    with open('excel_data.json', 'w', encoding='utf-8') as f:
        json.dump(data, f, ensure_ascii=False, indent=2)
    print("Data extracted to excel_data.json")
except Exception as e:
    import traceback
    print(f'Error: {str(e)}', file=sys.stderr)
    traceback.print_exc(file=sys.stderr)
    sys.exit(1)